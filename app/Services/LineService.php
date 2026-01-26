<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineService
{
    public static function sendPushMessage($message, $userId = null)
    {
        // ✅ เพิ่มตรงนี้: ถ้าใน .env ตั้งเป็น false ให้จบการทำงานทันที (ไม่ส่ง ไม่ error)
        if (env('LINE_ENABLE', true) == false) {
            return true;
        }

        // ถ้าไม่ได้ระบุผู้รับ ให้ส่งหา Admin (ตัวเราเอง)
        $targetUserId = $userId ?? env('LINE_ADMIN_USER_ID');
        $token = env('LINE_CHANNEL_ACCESS_TOKEN');

        if (!$targetUserId || !$token) {
            Log::error("LINE Config missing");
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post('https://api.line.me/v2/bot/message/push', [
                'to' => $targetUserId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error("LINE Send Failed: " . $response->body());
            }

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("LINE Error: " . $e->getMessage());
            return false;
        }
    }
}
