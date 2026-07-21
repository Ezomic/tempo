<?php

declare(strict_types=1);

namespace App\Services\Garmin;

use adriangibbons\phpFITFileAnalysis;
use App\DataObjects\ParsedActivity;

class FitParser
{
    public function parseFile(string $path): ParsedActivity
    {
        return $this->extract(new phpFITFileAnalysis($path));
    }

    public function parseData(string $bytes): ParsedActivity
    {
        return $this->extract(new phpFITFileAnalysis($bytes, ['input_is_data' => true]));
    }

    private function extract(phpFITFileAnalysis $fit): ParsedActivity
    {
        $hr = $fit->data_mesgs['record']['heart_rate'] ?? [];

        if (! is_array($hr)) {
            $hr = [];
        }

        $samples = [];
        foreach ($hr as $timestamp => $bpm) {
            if (is_numeric($timestamp) && is_numeric($bpm) && (int) $bpm > 0) {
                $samples[(int) $timestamp] = (int) $bpm;
            }
        }

        ksort($samples);

        return new ParsedActivity($samples);
    }
}
