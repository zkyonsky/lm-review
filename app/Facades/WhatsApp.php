<?php

namespace App\Facades;

use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\Subject;
use App\Models\User;
use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static WhatsAppGatewayInterface driver(?string $name = null)
 * @method static ?string normalizePhone(?string $phone)
 * @method static bool send(User|string $recipient, string $message, ?string $driverName = null)
 * @method static bool notifySubjectAssignment(User $user, Subject $subject, string $role)
 * @method static bool notifyReviewAssignment(User $user, MaterialVersion $version, ?Review $review = null)
 * @method static bool notifyNewVersionUploaded(User $user, MaterialVersion $version, ?Review $review = null)
 * 
 * @see WhatsAppService
 */
class WhatsApp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WhatsAppService::class;
    }
}
