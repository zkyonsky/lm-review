<?php

namespace App\Jobs;

use App\Models\MaterialVersion;
use App\Models\ScormPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use SimpleXMLElement;
use Exception;

class ProcessScormPackage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MaterialVersion $version
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $package = ScormPackage::firstOrCreate(
            ['material_version_id' => $this->version->id],
            ['status' => 'processing']
        );

        try {
            $zipPath = Storage::disk('local')->path($this->version->file_path);
            
            if (!file_exists($zipPath)) {
                throw new Exception("File ZIP tidak ditemukan.");
            }

            $extractDir = 'scorm/' . $this->version->id;
            $extractPath = Storage::disk('public')->path($extractDir);

            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                if (!is_dir($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new Exception("Gagal membuka file ZIP.");
            }

            // Search for imsmanifest.xml in $extractPath or its subfolders
            $manifestPath = null;
            if (file_exists($extractPath . '/imsmanifest.xml')) {
                $manifestPath = $extractPath . '/imsmanifest.xml';
            } else {
                $found = glob($extractPath . '/*/imsmanifest.xml');
                if (!empty($found)) {
                    $manifestPath = $found[0];
                } else {
                    $foundDeep = glob($extractPath . '/*/*/imsmanifest.xml');
                    if (!empty($foundDeep)) {
                        $manifestPath = $foundDeep[0];
                    }
                }
            }

            if ($manifestPath && file_exists($manifestPath)) {
                // Manifest found: Standard SCORM package
                $manifestDir = dirname($manifestPath);
                $relativeSub = trim(str_replace($extractPath, '', $manifestDir), '/\\');
                $actualExtractDir = $relativeSub ? $extractDir . '/' . str_replace('\\', '/', $relativeSub) : $extractDir;

                $manifestContent = file_get_contents($manifestPath);
                $xml = new SimpleXMLElement($manifestContent);
                
                // Get namespaces
                $namespaces = $xml->getNamespaces(true);
                $xml->registerXPathNamespace('default', current($namespaces) ?: 'http://www.imsglobal.org/xsd/imscp_v1p1');

                $schemaversion = (string) ($xml->metadata->schemaversion ?? '');
                $scormVersion = (strpos(strtolower($schemaversion), '2004') !== false || strpos(strtolower($schemaversion), '1.3') !== false) ? '2004' : '1.2';
                
                $packageIdentifier = (string) ($xml['identifier'] ?? 'scorm_' . $this->version->id);
                
                // Find default launch
                $organizations = $xml->organizations;
                $defaultOrgId = (string) ($organizations['default'] ?? '');
                
                // Extract SCOs
                $resources = [];
                if (isset($xml->resources->resource)) {
                    foreach ($xml->resources->resource as $res) {
                        $resId = (string) $res['identifier'];
                        $href = (string) $res['href'];
                        $scormType = (string) ($res->attributes('adlcp', true)->scormType ?? $res->attributes('adlcp', true)->scormtype ?? 'sco');
                        
                        // If href does not exist on disk, fallback to declared files or standard html entry files
                        if (empty($href) || !file_exists($manifestDir . '/' . $href)) {
                            $resolvedHref = null;
                            if (isset($res->file)) {
                                foreach ($res->file as $f) {
                                    $fHref = (string) $f['href'];
                                    if ($fHref !== '' && file_exists($manifestDir . '/' . $fHref) && preg_match('/\.(html?|htm)$/i', $fHref)) {
                                        $resolvedHref = $fHref;
                                        break;
                                    }
                                }
                            }

                            if (!$resolvedHref) {
                                foreach (['index.html', 'index_html5.html', 'story.html', 'story_html5.html'] as $cand) {
                                    if (file_exists($manifestDir . '/' . $cand)) {
                                        $resolvedHref = $cand;
                                        break;
                                    }
                                }
                            }

                            if ($resolvedHref) {
                                $href = $resolvedHref;
                            }
                        }

                        $resources[$resId] = [
                            'href' => $href,
                            'scormType' => strtolower($scormType)
                        ];
                    }
                }

                $scos = [];
                $launchPath = null;
                $sortOrder = 0;

                if (isset($organizations->organization)) {
                    foreach ($organizations->organization as $org) {
                        if ($defaultOrgId && (string)$org['identifier'] !== $defaultOrgId && empty($scos)) {
                            continue;
                        }
                        
                        if (isset($org->item)) {
                            $this->parseItems($org->item, $resources, $scos, $sortOrder);
                        }
                    }
                }

                if (empty($scos)) {
                    foreach ($resources as $res) {
                        if (!empty($res['href'])) {
                            $scos[] = [
                                'identifier' => 'sco_1',
                                'title' => (string) ($xml->organizations->organization->title ?? $this->version->material->title),
                                'launch_path' => $res['href'],
                                'sort_order' => 0
                            ];
                            break;
                        }
                    }
                }

                if (count($scos) > 0) {
                    foreach ($scos as &$sco) {
                        if (!file_exists($manifestDir . '/' . $sco['launch_path'])) {
                            foreach (['index.html', 'index_html5.html', 'story.html', 'story_html5.html'] as $cand) {
                                if (file_exists($manifestDir . '/' . $cand)) {
                                    $sco['launch_path'] = $cand;
                                    break;
                                }
                            }
                        }
                    }
                    unset($sco);

                    $launchPath = $scos[0]['launch_path'];
                } else {
                    throw new Exception("Tidak ditemukan item SCO atau resource yang valid di dalam manifest.");
                }

                $package->update([
                    'scorm_version' => $scormVersion,
                    'identifier' => $packageIdentifier,
                    'title' => (string) ($xml->organizations->organization->title ?? $this->version->material->title),
                    'extract_path' => $actualExtractDir,
                    'launch_path' => $launchPath,
                    'status' => 'ready',
                    'error_message' => null,
                ]);

                // Save SCOs
                $package->scos()->delete();
                $package->scos()->createMany($scos);

            } else {
                // No imsmanifest.xml: Check for HTML5 / Storyline web export
                $htmlCandidates = [
                    'index_html5.html',
                    'index.html',
                    'story.html',
                    'story_html5.html',
                ];

                $entryFile = null;
                $entryDir = null;

                // Check root first
                foreach ($htmlCandidates as $cand) {
                    if (file_exists($extractPath . '/' . $cand)) {
                        $entryFile = $cand;
                        $entryDir = $extractPath;
                        break;
                    }
                }

                // Check 1 level deep (e.g., MP 1 Rev1/index_html5.html)
                if (!$entryFile) {
                    foreach ($htmlCandidates as $cand) {
                        $found = glob($extractPath . '/*/' . $cand);
                        if (!empty($found)) {
                            $entryFile = basename($found[0]);
                            $entryDir = dirname($found[0]);
                            break;
                        }
                    }
                }

                // Check 2 levels deep
                if (!$entryFile) {
                    foreach ($htmlCandidates as $cand) {
                        $found = glob($extractPath . '/*/*/' . $cand);
                        if (!empty($found)) {
                            $entryFile = basename($found[0]);
                            $entryDir = dirname($found[0]);
                            break;
                        }
                    }
                }

                if ($entryFile && $entryDir) {
                    $relativeSub = trim(str_replace($extractPath, '', $entryDir), '/\\');
                    $actualExtractDir = $relativeSub ? $extractDir . '/' . str_replace('\\', '/', $relativeSub) : $extractDir;

                    $package->update([
                        'scorm_version' => '1.2',
                        'identifier' => 'web_package_' . $this->version->id,
                        'title' => $this->version->material->title,
                        'extract_path' => $actualExtractDir,
                        'launch_path' => $entryFile,
                        'status' => 'ready',
                        'error_message' => null,
                    ]);

                    $package->scos()->delete();
                    $package->scos()->create([
                        'identifier' => 'sco_1',
                        'title' => $this->version->material->title,
                        'launch_path' => $entryFile,
                        'sort_order' => 0,
                    ]);
                } else {
                    throw new Exception("File manifest (imsmanifest.xml) atau file konten web utama (index.html / index_html5.html) tidak ditemukan di dalam paket ZIP.");
                }
            }

        } catch (Exception $e) {
            $package->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            \Log::error('SCORM Processing Error for Version ID ' . $this->version->id . ': ' . $e->getMessage());
        }
    }

    private function parseItems($items, $resources, &$scos, &$sortOrder)
    {
        foreach ($items as $item) {
            $identifierref = (string) $item['identifierref'];
            $identifier = (string) $item['identifier'];
            $title = (string) $item->title;

            if ($identifierref && isset($resources[$identifierref])) {
                $resource = $resources[$identifierref];
                if ($resource['scormType'] === 'sco' || $resource['href'] !== '') {
                    $scos[] = [
                        'identifier' => $identifier,
                        'title' => $title ?: $identifier,
                        'launch_path' => $resource['href'],
                        'sort_order' => $sortOrder++
                    ];
                }
            }

            if (isset($item->item)) {
                $this->parseItems($item->item, $resources, $scos, $sortOrder);
            }
        }
    }
}
