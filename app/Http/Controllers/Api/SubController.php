<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SubController extends Controller
{
    public function __construct(private SubscriptionService $subscription) {}

    /** GET /sub/{token} —— 公开订阅下发（客户端不登录，凭 token） */
    public function show(string $token)
    {
        $user = User::where('invite_token', $token)->first();
        abort_if($user === null, 404);

        try {
            $yaml = $this->subscription->generateClash($user);
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return response($yaml, Response::HTTP_OK, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Profile-Update-Interval' => '24',
            'Subscription-Userinfo' => $this->userInfoHeader($user),
        ]);
    }

    /** 客户端显示流量/到期用的标准头 */
    private function userInfoHeader(User $user): string
    {
        $expire = $user->class_expire ? $user->class_expire->timestamp : 0;

        return sprintf(
            'upload=%d; download=%d; total=%d; expire=%d',
            $user->u, $user->d, $user->transfer_enable, $expire
        );
    }
}
