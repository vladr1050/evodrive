<?php

namespace App\Http\Controllers;

use App\Models\CarCommand;
use App\Models\Driver;
use App\Models\ShiftPolicy;
use App\Services\CarControlService;
use App\Services\TelegramNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Telegram webhook for car control: driver identifies by telegram_id (chat_id).
 * Incoming: message text "car" / "Car control" or callback_query from inline buttons.
 */
class TelegramCarControlWebhookController extends Controller
{
    public function __construct(
        protected CarControlService $carControl,
        protected TelegramNotifier $telegram
    ) {
    }

    public function handle(Request $request): Response
    {
        $payload = $request->all();
        if (empty($payload)) {
            return response('', 200);
        }

        try {
            if (isset($payload['callback_query'])) {
                $this->handleCallbackQuery($payload['callback_query']);
            } elseif (isset($payload['message'])) {
                $this->handleMessage($payload['message']);
            }
        } catch (\Throwable $e) {
            Log::channel('stack')->error('TelegramCarControlWebhook: exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response('', 200);
    }

    private function handleMessage(array $message): void
    {
        $chat = $message['chat'] ?? [];
        $chatId = (string) ($chat['id'] ?? '');
        $chatType = (string) ($chat['type'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === '' || $text === '') {
            return;
        }

        // Car control only in private chats. In groups we don't respond.
        if ($chatType !== 'private') {
            return;
        }

        $trigger = mb_strtolower($text);
        $carTriggers = ['car', 'car control', 'авто', 'машина', '/car'];
        if (! in_array($trigger, $carTriggers, true)) {
            return;
        }

        $driver = Driver::where('telegram_id', $chatId)->first();
        if (! $driver) {
            $this->telegram->sendToChat($chatId, 'You are not registered as a driver. Please contact support.');
            return;
        }

        $this->sendCarControlCard($driver->id, $chatId);
    }

    private function handleCallbackQuery(array $callback): void
    {
        $chat = $callback['message']['chat'] ?? [];
        $chatId = (string) ($chat['id'] ?? '');
        $chatType = (string) ($chat['type'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        if ($chatId === '' || $data === '') {
            return;
        }

        if ($chatType !== 'private') {
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        $driver = Driver::where('telegram_id', $chatId)->first();
        if (! $driver) {
            $this->telegram->answerCallbackQuery($callbackId, 'Not registered');
            return;
        }

        if ($data === 'car_back') {
            $this->telegram->answerCallbackQuery($callbackId);
            $this->sendCarControlCard($driver->id, $chatId);
            return;
        }

        if ($data === 'my_next_shift') {
            $this->telegram->answerCallbackQuery($callbackId);
            $this->sendNextShift($driver->id, $chatId);
            return;
        }

        if ($data === 'end_shift_confirm') {
            $this->telegram->answerCallbackQuery($callbackId);
            $result = $this->carControl->executeAction($driver->id, CarCommand::ACTION_END_SHIFT);
            $this->telegram->sendToChat($chatId, $result['ok'] ? '✅ ' . $result['message'] : '❌ ' . $result['message']);
            return;
        }

        if ($data === 'end_shift_cancel') {
            $this->telegram->answerCallbackQuery($callbackId);
            $this->sendCarControlCard($driver->id, $chatId);
            return;
        }

        if ($data === 'end_shift') {
            $this->telegram->answerCallbackQuery($callbackId);
            $confirmText = "Are you sure you want to end the shift? The engine will be locked and the car closed.";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Confirm', 'callback_data' => 'end_shift_confirm']],
                    [['text' => 'Cancel', 'callback_data' => 'end_shift_cancel']],
                ],
            ];
            $this->telegram->editMessageText($chatId, $messageId, $confirmText, $keyboard);
            return;
        }

        $actionMap = [
            'start_shift' => CarCommand::ACTION_START_SHIFT,
            'open_car' => CarCommand::ACTION_OPEN_CAR,
            'close_car' => CarCommand::ACTION_CLOSE_CAR,
        ];
        if (! isset($actionMap[$data])) {
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        $this->telegram->answerCallbackQuery($callbackId, 'Sending…');
        $result = $this->carControl->executeAction($driver->id, $actionMap[$data]);
        $reply = $result['ok'] ? '✅ ' . $result['message'] : '❌ ' . $result['message'];
        $this->telegram->editMessageText($chatId, $messageId, $reply, $this->backKeyboard());
    }

    private function sendCarControlCard(int $driverId, string $chatId): void
    {
        $context = $this->carControl->getDriverCarControlContext($driverId);

        if (! ($context['allowed'] ?? false)) {
            $reason = $context['reason'] ?? 'no_shift';
            $messages = [
                'too_early' => 'More than 45 minutes until shift start.',
                'too_late' => 'Shift ended, control window closed.',
                'no_shift' => 'No shift found.',
                'car_not_configured' => 'Vehicle not configured (no SIM / number).',
            ];
            $text = $messages[$reason] ?? 'No active shift.';
            $keyboard = ['inline_keyboard' => [[['text' => 'My next shift', 'callback_data' => 'my_next_shift']]]];
            $this->telegram->sendToChat($chatId, $text, $keyboard);
            return;
        }

        $shift = $context['shift'];
        $vehicle = $context['vehicle'];
        $now = now();
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $start = $shift->starts_at->copy()->setTimezone($tz)->format('d.m H:i');
        $end = $shift->ends_at->copy()->setTimezone($tz)->format('d.m H:i');
        $label = trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? ''));
        $plate = $vehicle->registration_number ?? '—';
        $phoneMask = $this->maskPhone($vehicle->sim);

        $lines = [
            "🚗 Car: {$label}, {$plate}",
            "📱 {$phoneMask}",
            "🕐 Shift: {$start} – {$end}",
        ];
        if ($now->lt($shift->starts_at)) {
            $lines[] = '⏳ Starts in ' . $now->diffForHumans($shift->starts_at, ['syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);
        } elseif ($now->gt($shift->ends_at)) {
            $lines[] = '⏳ Window closes in ' . $now->diffForHumans($shift->ends_at->addMinutes(config('car_control.window_minutes', 45)), ['syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);
        } else {
            $lines[] = '⏳ Ends in ' . $now->diffForHumans($shift->ends_at, ['syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);
        }
        $text = implode("\n", $lines);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Start shift', 'callback_data' => 'start_shift']],
                [['text' => '🔓 Open car', 'callback_data' => 'open_car'], ['text' => '🔒 Close car', 'callback_data' => 'close_car']],
                [['text' => '⛔ End shift', 'callback_data' => 'end_shift']],
                [['text' => '↩️ Back', 'callback_data' => 'car_back']],
            ],
        ];
        $this->telegram->sendToChat($chatId, $text, $keyboard);
    }

    private function sendNextShift(int $driverId, string $chatId): void
    {
        $next = \App\Models\Shift::query()
            ->where('driver_id', $driverId)
            ->where('status', \App\Enums\ShiftStatus::Booked)
            ->where('starts_at', '>', now())
            ->with('vehicle', 'station')
            ->orderBy('starts_at')
            ->first();

        if (! $next) {
            $this->telegram->sendToChat($chatId, 'No upcoming shifts.');
            return;
        }

        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $start = $next->starts_at->copy()->setTimezone($tz)->format('d.m.Y H:i');
        $end = $next->ends_at->copy()->setTimezone($tz)->format('H:i');
        $v = $next->vehicle;
        $label = $v ? trim(($v->brand ?? '') . ' ' . ($v->model ?? '')) . ', ' . ($v->registration_number ?? '') : '—';
        $st = $next->station ? $next->station->name : '—';
        $text = "Next shift:\n{$start} – {$end}\nCar: {$label}\nStation: {$st}\n\nCar control will be available 45 min before start.";
        $this->telegram->sendToChat($chatId, $text, $this->backKeyboard());
    }

    private function backKeyboard(): array
    {
        return ['inline_keyboard' => [[['text' => '↩️ Back', 'callback_data' => 'car_back']]]];
    }

    private function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '—';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return '***';
        }

        return substr($digits, 0, 3) . '****' . substr($digits, -2);
    }
}
