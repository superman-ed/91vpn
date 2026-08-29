<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyTraffic;
use App\Models\SubscribeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NodeApiController extends Controller
{
    /** GET /api/node —— 连接凭证(订阅链接 / UUID / 连接密码) */
    public function show(Request $request)
    {
        return response()->json(['ret' => 1, 'data' => $this->credentials($request->user())]);
    }

    /** POST /api/node/reset-sub —— 重置订阅链接(换 invite_token) */
    public function resetSub(Request $request)
    {
        $user = $request->user();
        $user->update(['invite_token' => Str::random(32)]);

        return response()->json([
            'ret' => 1,
            'data' => $this->credentials($user->fresh()),
            'msg' => '订阅链接已重置，新链接约 10 分钟内生效，请重新导入客户端',
        ]);
    }

    /** POST /api/node/reset-credential —— 重置连接凭证(同时换 UUID + 连接密码) */
    public function resetCredential(Request $request)
    {
        $user = $request->user();
        $user->update(['passwd' => Str::lower(Str::random(6)), 'uuid' => (string) Str::uuid()]);

        return response()->json([
            'ret' => 1,
            'data' => $this->credentials($user->fresh()),
            'msg' => '连接凭证（UUID）已重置，约 10 分钟内生效，请重新导入订阅',
        ]);
    }

    /** GET /api/traffic —— 每日流量(近30天) */
    public function traffic(Request $request)
    {
        $records = DailyTraffic::where('user_id', $request->user()->id)
            ->orderByDesc('date')->limit(30)->get();

        return response()->json(['ret' => 1, 'data' => [
            'total' => (int) $records->sum(fn ($r) => $r->u + $r->d),
            'records' => $records->map(fn (DailyTraffic $r) => [
                'date' => $r->date?->toDateString(),
                'u' => (int) $r->u,
                'd' => (int) $r->d,
                'total' => (int) ($r->u + $r->d),
            ])->values(),
        ]]);
    }

    /** GET /api/subscribe-log —— 订阅拉取记录(近30条) */
    public function subscribeLog(Request $request)
    {
        $logs = SubscribeLog::where('user_id', $request->user()->id)
            ->latest('fetched_at')->limit(30)->get()
            ->map(fn (SubscribeLog $l) => [
                'type' => $l->type,
                'ip' => $l->ip,
                'location' => $l->location,
                'client' => $l->client,
                'fetched_at' => $l->fetched_at?->toDateTimeString(),
            ])->values();

        return response()->json(['ret' => 1, 'data' => $logs]);
    }

    /** 连接凭证出参 */
    private function credentials($user): array
    {
        return [
            'uuid' => $user->uuid,
            'passwd' => $user->passwd,
            'sub_token' => $user->invite_token,
            'sub_url' => url('/sub/'.$user->invite_token),
        ];
    }
}
