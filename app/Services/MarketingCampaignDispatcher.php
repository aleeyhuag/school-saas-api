<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\MarketingSuppression;
use App\Models\PlatformLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class MarketingCampaignDispatcher
{
    public function release(int $limit = 10): int
    {
        $limit = max(1, min($limit, 100));
        $rows = collect();
        foreach (EmailCampaign::whereIn('status', ['queued', 'sending'])->whereNull('cancelled_at')->orderBy('id')->get() as $campaign) {
            if ($rows->count() >= $limit) break;
            $rows = $rows->merge($this->reserve($campaign, $limit - $rows->count()));
        }
        if ($rows->isEmpty()) {
            $this->completeFinishedCampaigns();
            return 0;
        }
        foreach ($rows->groupBy('email_campaign_id') as $campaignRows) $this->sendCampaignRows($campaignRows);
        $this->completeFinishedCampaigns();
        return $rows->count();
    }

    protected function reserve(EmailCampaign $campaign, int $limit)
    {
        return \DB::transaction(function () use ($campaign, $limit) {
            $locked = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'cancelled') return collect();
            $rows = $locked->recipients()->whereIn('status', ['pending', 'queued'])
                ->orderByRaw("CASE WHEN status = 'queued' THEN 0 ELSE 1 END")
                ->orderBy('id')->limit($limit)->lockForUpdate()->get();
            if ($rows->isEmpty()) return collect();
            $locked->update(['status' => 'sending', 'started_at' => $locked->started_at ?? now()]);
            foreach ($rows as $row) $row->update(['status' => 'sending', 'released_at' => $row->released_at ?? now()]);
            return $rows;
        });
    }

    protected function sendCampaignRows($rows): void
    {
        $campaign = $rows->first()->campaign;
        $payload = [];
        $prepared = [];
        foreach ($rows as $row) {
            $lead = PlatformLead::where('email', $row->email)->first();
            if ($lead?->unsubscribed || MarketingSuppression::where('email', $row->email)->exists()) {
                $row->update(['status' => 'unsubscribed']);
                continue;
            }
            $unsubscribeUrl = URL::temporarySignedRoute('marketing.unsubscribe', now()->addYear(), ['email' => $row->email]);
            $body = str_replace(['{{name}}', '{{school_name}}'], [$row->name ?: 'there', $row->school_name ?: 'your school'], $campaign->html_body);
            $body .= '<hr><p style="font-size:12px;color:#666">You are receiving this Skulag promotional message. <a href="'.$unsubscribeUrl.'">Unsubscribe</a></p>';
            $prepared[$row->id] = $row;
            $payload[] = [
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'to' => [$row->email], 'subject' => $campaign->subject, 'html' => $body,
                'headers' => ['List-Unsubscribe' => '<'.$unsubscribeUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
            ];
        }
        if (! $payload) return;
        try {
            if (config('mail.default') === 'resend' && env('RESEND_API_KEY')) {
                $response = Http::withToken(env('RESEND_API_KEY'))->acceptJson()->timeout(30)
                    ->withHeaders(['Idempotency-Key' => 'skulag-campaign-'.$campaign->id.'-'.implode('-', array_keys($prepared))])
                    ->post('https://api.resend.com/emails/batch', $payload);
                if ($response->failed()) throw new \RuntimeException('Resend batch failed (HTTP '.$response->status().'): '.$response->body());
            } else {
                foreach ($payload as $item) {
                    Mail::html($item['html'], function ($message) use ($item) {
                        $message->to($item['to'][0])->subject($item['subject']);
                        foreach ($item['headers'] as $key => $value) $message->getSymfonyMessage()->getHeaders()->addTextHeader($key, $value);
                    });
                }
            }
            foreach ($prepared as $row) { $row->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]); $campaign->increment('sent_count'); }
        } catch (Throwable $e) {
            foreach ($prepared as $row) { $row->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 1000)]); $campaign->increment('failed_count'); }
        }
    }

    protected function completeFinishedCampaigns(): void
    {
        EmailCampaign::whereIn('status', ['queued', 'sending'])->whereNull('cancelled_at')->each(function (EmailCampaign $campaign) {
            if (! $campaign->recipients()->whereIn('status', ['pending', 'queued', 'sending'])->exists()) $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        });
    }
}
