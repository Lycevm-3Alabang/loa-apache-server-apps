<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\EventAttendee;
use Illuminate\Database\Eloquent\Builder;

class CertificateSource
{
    public const MODE_FILE = 'file';
    public const MODE_TEMPLATE = 'template';

    public const SOURCE_UPLOADED = 'uploaded';
    public const SOURCE_SYSTEM_GENERATED = 'system-generated';

    public function resolve(Certificate $certificate): string
    {
        $attendee = $certificate->relationLoaded('attendee')
            ? $certificate->getRelation('attendee')
            : EventAttendee::where('certificate_id', $certificate->id)->first();

        if ($attendee) {
            $metadata = $attendee->metadata ?? [];
            $mode = is_array($metadata) ? ($metadata['generation_mode'] ?? self::MODE_TEMPLATE) : self::MODE_TEMPLATE;

            return in_array($mode, [self::MODE_TEMPLATE, self::MODE_FILE], true)
                ? $mode
                : self::MODE_TEMPLATE;
        }

        $metadata = $certificate->metadata ?? [];
        $mode = is_array($metadata) ? ($metadata['generation_mode'] ?? self::MODE_TEMPLATE) : self::MODE_TEMPLATE;

        return in_array($mode, [self::MODE_TEMPLATE, self::MODE_FILE], true)
            ? $mode
            : self::MODE_TEMPLATE;
    }

    public function sourceToMode(string $source): ?string
    {
        return match ($source) {
            self::SOURCE_UPLOADED => self::MODE_FILE,
            self::SOURCE_SYSTEM_GENERATED => self::MODE_TEMPLATE,
            default => null,
        };
    }

    public function applySourceFilter(Builder $query, string $source): void
    {
        $mode = $this->sourceToMode($source);

        if ($mode === self::MODE_FILE) {
            $query->where(function ($q) {
                $q->whereExists(function ($sq) {
                    $sq->selectRaw('1')->from('event_attendees')
                        ->whereColumn('event_attendees.certificate_id', 'certificates.id')
                        ->where('event_attendees.metadata->generation_mode', self::MODE_FILE);
                })->orWhere(function ($q2) {
                    $q2->whereNotExists(function ($sq) {
                        $sq->selectRaw('1')->from('event_attendees')
                            ->whereColumn('event_attendees.certificate_id', 'certificates.id');
                    })->where('certificates.metadata->generation_mode', self::MODE_FILE);
                });
            });

            return;
        }

        $query->where(function ($q) {
            $q->whereExists(function ($sq) {
                $sq->selectRaw('1')->from('event_attendees')
                    ->whereColumn('event_attendees.certificate_id', 'certificates.id')
                    ->where(function ($w) {
                        $w->where('event_attendees.metadata->generation_mode', '!=', self::MODE_FILE)
                            ->orWhereNull('event_attendees.metadata->generation_mode');
                    });
            })->orWhere(function ($q2) {
                $q2->whereNotExists(function ($sq) {
                    $sq->selectRaw('1')->from('event_attendees')
                        ->whereColumn('event_attendees.certificate_id', 'certificates.id');
                })->where(function ($w) {
                    $w->where('certificates.metadata->generation_mode', '!=', self::MODE_FILE)
                        ->orWhereNull('certificates.metadata->generation_mode');
                });
            });
        });
    }
}
