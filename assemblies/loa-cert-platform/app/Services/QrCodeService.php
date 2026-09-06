<?php

namespace App\Services;

class QrCodeService
{
    private const ERROR_CORRECTION_M = 1;

    private const FORMAT_INFO_MASK = 0x5412;

    private const ALIGNMENT_PATTERNS = [
        2 => [],
        3 => [6, 18],
        4 => [6, 22],
        5 => [6, 26],
        6 => [6, 30],
        7 => [6, 34],
        8 => [6, 22, 38],
        9 => [6, 24, 42],
        10 => [6, 26, 46],
        11 => [6, 28, 50],
        12 => [6, 30, 54],
        13 => [6, 32, 58],
        14 => [6, 34, 62],
        15 => [6, 26, 46, 66],
        16 => [6, 26, 48, 70],
        17 => [6, 26, 50, 74],
        18 => [6, 30, 54, 78],
        19 => [6, 30, 56, 82],
        20 => [6, 30, 58, 86],
        21 => [6, 34, 62, 90],
        22 => [6, 28, 50, 72, 94],
        23 => [6, 26, 50, 74, 98],
        24 => [6, 30, 54, 78, 102],
        25 => [6, 28, 54, 80, 106],
        26 => [6, 32, 58, 84, 110],
        27 => [6, 30, 58, 86, 114],
        28 => [6, 34, 62, 90, 118],
        29 => [6, 26, 50, 74, 98, 122],
        30 => [6, 30, 54, 78, 102, 126],
        31 => [6, 26, 52, 78, 104, 130],
        32 => [6, 30, 56, 82, 108, 134],
        33 => [6, 34, 60, 86, 112, 138],
        34 => [6, 30, 58, 86, 114, 142],
        35 => [6, 34, 62, 90, 118, 146],
        36 => [6, 30, 54, 78, 102, 126, 150],
        37 => [6, 24, 50, 76, 102, 128, 154],
        38 => [6, 28, 54, 80, 106, 132, 158],
        39 => [6, 32, 58, 84, 110, 136, 162],
        40 => [6, 26, 54, 82, 110, 138, 166],
    ];

    public function toDataUri(string $text): string
    {
        $qrMatrix = $this->encode($text);
        $pngData = $this->renderPng($qrMatrix);

        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    public function toBase64(string $text): string
    {
        $qrMatrix = $this->encode($text);
        $pngData = $this->renderPng($qrMatrix);

        return base64_encode($pngData);
    }

    private function encode(string $text): array
    {
        $data = $this->utf8ToBytes($text);
        $version = $this->minVersion(count($data));
        $size = $version * 4 + 17;

        $modules = array_fill(0, $size, array_fill(0, $size, null));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $this->placeFunctionPatterns($modules, $isFunction, $version, $size);

        $dataBits = $this->encodeData($data, $version);
        $dataBits = $this->applyErrorCorrection($dataBits, $version);

        $this->placeDataBits($modules, $isFunction, $dataBits, $size);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($modules, $isFunction, $mask, $size);
            $this->placeFormatInfo($modules, $isFunction, $version, $mask, $size);
            $penalty = $this->calculatePenalty($modules, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($modules, $isFunction, $mask, $size);
        }

        $this->applyMask($modules, $isFunction, $bestMask, $size);
        $this->placeFormatInfo($modules, $isFunction, $version, $bestMask, $size);

        return $modules;
    }

    private function utf8ToBytes(string $text): array
    {
        $bytes = [];
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $bytes[] = ord($text[$i]);
        }
        return $bytes;
    }

    private function minVersion(int $dataLength): int
    {
        for ($v = 1; $v <= 40; $v++) {
            $capacity = $this->dataCapacity($v);
            if ($dataLength <= $capacity) {
                return $v;
            }
        }
        return 40;
    }

    private function dataCapacity(int $version): int
    {
        $totalModules = ($version * 4 + 17) ** 2;
        $functionModules = $this->countFunctionModules($version);
        $dataModules = $totalModules - $functionModules;
        $codewords = (int) floor($dataModules / 8);
        $ecCodewords = $this->ecCodewordsPerBlock($version);
        $blocks = $this->totalBlocks($version);
        $ecTotal = $ecCodewords * $blocks;
        return $codewords - $ecTotal;
    }

    private function countFunctionModules(int $version): int
    {
        $size = $version * 4 + 17;
        $count = 0;

        $count += 8 * 3 + 1;
        $count += 2 * 15;

        $alignments = self::ALIGNMENT_PATTERNS[$version] ?? [];
        foreach ($alignments as $row) {
            foreach ($alignments as $col) {
                if (($row <= 8 && $col <= 8) || ($row <= 8 && $col >= $size - 8) || ($row >= $size - 8 && $col <= 8)) {
                    continue;
                }
                $count += 25;
            }
        }

        $count += ($size - 16) * 2;

        $count += 3 * 6 + 1;

        return $count;
    }

    private function ecCodewordsPerBlock(int $version): int
    {
        $table = [
            1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24,
            6 => 16, 7 => 18, 8 => 22, 9 => 22, 10 => 26,
            11 => 30, 12 => 22, 13 => 22, 14 => 24, 15 => 24,
            16 => 28, 17 => 28, 18 => 26, 19 => 26, 20 => 26,
            21 => 26, 22 => 28, 23 => 28, 24 => 28, 25 => 28,
            26 => 28, 27 => 28, 28 => 28, 29 => 28, 30 => 28,
            31 => 28, 32 => 28, 33 => 28, 34 => 28, 35 => 28,
            36 => 28, 37 => 28, 38 => 28, 39 => 28, 40 => 28,
        ];
        return $table[$version] ?? 10;
    }

    private function totalBlocks(int $version): int
    {
        $table = [
            1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 2,
            6 => 4, 7 => 4, 8 => 4, 9 => 4, 10 => 6,
            11 => 6, 12 => 6, 13 => 6, 14 => 8, 15 => 8,
            16 => 8, 17 => 8, 18 => 10, 19 => 10, 20 => 10,
            21 => 10, 22 => 12, 23 => 12, 24 => 12, 25 => 12,
            26 => 14, 27 => 14, 28 => 14, 29 => 14, 30 => 16,
            31 => 16, 32 => 16, 33 => 16, 34 => 18, 35 => 18,
            36 => 18, 37 => 18, 38 => 20, 39 => 20, 40 => 20,
        ];
        return $table[$version] ?? 1;
    }

    private function blockSizes(int $version): array
    {
        $totalCodewords = $this->totalDataCodewords($version);
        $ecPerBlock = $this->ecCodewordsPerBlock($version);
        $numBlocks = $this->totalBlocks($version);

        $dataPerBlock = (int) floor($totalCodewords / $numBlocks);
        $largerBlocks = $totalCodewords % $numBlocks;
        $smallerBlocks = $numBlocks - $largerBlocks;

        return [
            'smaller' => $smallerBlocks,
            'larger' => $largerBlocks,
            'dataPerBlock' => $dataPerBlock,
        ];
    }

    private function totalDataCodewords(int $version): int
    {
        $totalModules = ($version * 4 + 17) ** 2;
        $functionModules = $this->countFunctionModules($version);
        $dataModules = $totalModules - $functionModules;
        return (int) floor($dataModules / 8);
    }

    private function encodeData(array $data, int $version): array
    {
        $bits = [];

        $mode = $this->selectMode($data, $version);
        $this->appendBits($bits, $mode, 4);

        $charCountBits = $this->charCountBits($mode, $version);
        $this->appendBits($bits, count($data), $charCountBits);

        foreach ($data as $byte) {
            $this->appendBits($bits, $byte, 8);
        }

        $capacity = $this->dataCapacity($version) * 8;
        $this->appendBits($bits, 0, min(4, $capacity - count($bits)));
        $padBits = (8 - (count($bits) % 8)) % 8;
        $this->appendBits($bits, 0, $padBits);

        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while (count($bits) < $capacity) {
            $this->appendBits($bits, $padBytes[$padIndex], 8);
            $padIndex = ($padIndex + 1) % 2;
        }

        return $bits;
    }

    private function selectMode(array $data, int $version): int
    {
        return 0b0100;
    }

    private function charCountBits(int $mode, int $version): int
    {
        if ($version <= 9) return 8;
        if ($version <= 26) return 16;
        return 16;
    }

    private function appendBits(array &$bits, int $value, int $numBits): void
    {
        for ($i = $numBits - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function applyErrorCorrection(array $dataBits, int $version): array
    {
        $dataBytes = [];
        for ($i = 0; $i < count($dataBits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | ($dataBits[$i + $j] ?? 0);
            }
            $dataBytes[] = $byte;
        }

        $blocks = $this->splitIntoBlocks($dataBytes, $version);
        $ecBlocks = [];
        foreach ($blocks as $block) {
            $ecBlocks[] = $this->reedSolomonEncode($block, $this->ecCodewordsPerBlock($version));
        }

        $result = [];
        $maxDataLen = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocks as $blockIndex => $block) {
                if (isset($block[$i])) {
                    $this->appendBits($result, $block[$i], 8);
                }
            }
        }
        for ($i = 0; $i < count($ecBlocks[0]); $i++) {
            foreach ($ecBlocks as $ecBlock) {
                $this->appendBits($result, $ecBlock[$i], 8);
            }
        }

        return $result;
    }

    private function splitIntoBlocks(array $dataBytes, int $version): array
    {
        $sizes = $this->blockSizes($version);
        $blocks = [];
        $offset = 0;

        for ($i = 0; $i < $sizes['smaller']; $i++) {
            $blocks[] = array_slice($dataBytes, $offset, $sizes['dataPerBlock']);
            $offset += $sizes['dataPerBlock'];
        }
        for ($i = 0; $i < $sizes['larger']; $i++) {
            $blocks[] = array_slice($dataBytes, $offset, $sizes['dataPerBlock'] + 1);
            $offset += $sizes['dataPerBlock'] + 1;
        }

        return $blocks;
    }

    private function reedSolomonEncode(array $data, int $ecCount): array
    {
        $generator = $this->rsGenerator($ecCount);
        $remainder = array_fill(0, $ecCount, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            for ($i = 0; $i < $ecCount; $i++) {
                $remainder[$i] ^= $this->gfMultiply($generator[$i + 1], $factor);
            }
        }

        return $remainder;
    }

    private function rsGenerator(int $degree): array
    {
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $newGen = array_pad([], count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $newGen[$j] ^= $gen[$j];
                $newGen[$j + 1] ^= $this->gfMultiply($gen[$j], $this->gfExp($i));
            }
            $gen = $newGen;
        }
        return $gen;
    }

    private function gfMultiply(int $a, int $b): int
    {
        $result = 0;
        for ($i = 0; $i < 8; $i++) {
            if ($b & (1 << $i)) {
                $result ^= $a;
            }
            $carry = $a & 0x80;
            $a = ($a << 1) & 0xFF;
            if ($carry) {
                $a ^= 0x1D;
            }
        }
        return $result & 0xFF;
    }

    private function gfExp(int $n): int
    {
        static $table = null;
        if ($table === null) {
            $table = [1];
            $val = 1;
            for ($i = 1; $i < 256; $i++) {
                $val = $this->gfMultiply($val, 2);
                $table[$i] = $val;
            }
        }
        return $table[$n % 255] ?? 1;
    }

    private function placeFunctionPatterns(array &$modules, array &$isFunction, int $version, int $size): void
    {
        $this->placeFinderPatterns($modules, $isFunction, $size);
        $this->placeAlignmentPatterns($modules, $isFunction, $version, $size);
        $this->placeTimingPatterns($modules, $isFunction, $size);
        $this->placeDarkModule($modules, $isFunction, $version);
    }

    private function placeFinderPatterns(array &$modules, array &$isFunction, int $size): void
    {
        $pattern = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1],
        ];

        $positions = [
            [0, 0],
            [0, $size - 7],
            [$size - 7, 0],
        ];

        foreach ($positions as [$row, $col]) {
            for ($r = 0; $r < 7; $r++) {
                for ($c = 0; $c < 7; $c++) {
                    $modules[$row + $r][$col + $c] = (bool) $pattern[$r][$c];
                    $isFunction[$row + $r][$col + $c] = true;
                }
            }
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $mr = $row + $r;
                    $mc = $col + $c;
                    if ($mr >= 0 && $mr < $size && $mc >= 0 && $mc < $size && !$isFunction[$mr][$mc]) {
                        $modules[$mr][$mc] = false;
                        $isFunction[$mr][$mc] = true;
                    }
                }
            }
        }
    }

    private function placeAlignmentPatterns(array &$modules, array &$isFunction, int $version, int $size): void
    {
        if ($version < 2) return;

        $positions = self::ALIGNMENT_PATTERNS[$version] ?? [];
        $pattern = [
            [1,1,1,1,1],
            [1,0,0,0,1],
            [1,0,1,0,1],
            [1,0,0,0,1],
            [1,1,1,1,1],
        ];

        foreach ($positions as $row) {
            foreach ($positions as $col) {
                if (($row <= 8 && $col <= 8) || ($row <= 8 && $col >= $size - 8) || ($row >= $size - 8 && $col <= 8)) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $modules[$row + $r][$col + $c] = (bool) $pattern[$r + 2][$c + 2];
                        $isFunction[$row + $r][$col + $c] = true;
                    }
                }
            }
        }
    }

    private function placeTimingPatterns(array &$modules, array &$isFunction, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $val = (bool) ($i % 2 === 0);
            if (!$isFunction[6][$i]) {
                $modules[6][$i] = $val;
                $isFunction[6][$i] = true;
            }
            if (!$isFunction[$i][6]) {
                $modules[$i][6] = $val;
                $isFunction[$i][6] = true;
            }
        }
    }

    private function placeDarkModule(array &$modules, array &$isFunction, int $version): void
    {
        $row = 4 * $version + 9;
        $modules[$row][8] = true;
        $isFunction[$row][8] = true;
    }

    private function placeDataBits(array &$modules, array &$isFunction, array $bits, int $size): void
    {
        $bitIndex = 0;
        $right = $size - 1;
        $upward = true;

        while ($right >= 0) {
            if ($right === 6) $right--;

            for ($i = 0; $i < $size; $i++) {
                $row = $upward ? $size - 1 - $i : $i;
                for ($j = 0; $j < 2; $j++) {
                    $col = $right - $j;
                    if ($col < 0) continue;
                    if (!$isFunction[$row][$col]) {
                        $modules[$row][$col] = (bool) ($bits[$bitIndex] ?? 0);
                        $bitIndex++;
                    }
                }
            }

            $right -= 2;
            $upward = !$upward;
        }
    }

    private function applyMask(array &$modules, array $isFunction, int $mask, int $size): void
    {
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if (!$isFunction[$row][$col] && $this->shouldMask($mask, $row, $col)) {
                    $modules[$row][$col] = !$modules[$row][$col];
                }
            }
        }
    }

    private function shouldMask(int $mask, int $row, int $col): bool
    {
        return match ($mask) {
            0 => ($row + $col) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $col % 3 === 0,
            3 => ($row + $col) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
            5 => ($row * $col) % 2 + ($row * $col) % 3 === 0,
            6 => (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0,
            7 => (($row + $col) % 2 + ($row * $col) % 3) % 2 === 0,
            default => false,
        };
    }

    private function placeFormatInfo(array &$modules, array &$isFunction, int $version, int $mask, int $size): void
    {
        $formatInfo = $this->getFormatBits($version, $mask);

        $positions1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        $positions2 = [
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8],
            [$size - 5, 8], [$size - 6, 8], [$size - 7, 8],
            [8, $size - 8], [8, $size - 7], [8, $size - 6], [8, $size - 5],
            [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1],
        ];

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($formatInfo >> $i) & 1);
            $modules[$positions1[$i][0]][$positions1[$i][1]] = $bit;
            $modules[$positions2[$i][0]][$positions2[$i][1]] = $bit;
            $isFunction[$positions1[$i][0]][$positions1[$i][1]] = true;
            $isFunction[$positions2[$i][0]][$positions2[$i][1]] = true;
        }
    }

    private function getFormatBits(int $version, int $mask): int
    {
        $ecLevel = self::ERROR_CORRECTION_M;
        $data = ($ecLevel << 3) | $mask;
        $bits = $data << 10;
        $gen = 0x537;
        for ($i = 4; $i >= 0; $i--) {
            if ($bits & (1 << ($i + 10))) {
                $bits ^= $gen << $i;
            }
        }
        return (($data << 10) | $bits) ^ self::FORMAT_INFO_MASK;
    }

    private function calculatePenalty(array $modules, int $size): int
    {
        $penalty = 0;

        for ($row = 0; $row < $size; $row++) {
            $count = 1;
            for ($col = 1; $col < $size; $col++) {
                if ($modules[$row][$col] === $modules[$row][$col - 1]) {
                    $count++;
                } else {
                    if ($count >= 5) $penalty += $count - 2;
                    $count = 1;
                }
            }
            if ($count >= 5) $penalty += $count - 2;
        }

        for ($col = 0; $col < $size; $col++) {
            $count = 1;
            for ($row = 1; $row < $size; $row++) {
                if ($modules[$row][$col] === $modules[$row - 1][$col]) {
                    $count++;
                } else {
                    if ($count >= 5) $penalty += $count - 2;
                    $count = 1;
                }
            }
            if ($count >= 5) $penalty += $count - 2;
        }

        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $c = $modules[$row][$col];
                if ($c === $modules[$row][$col + 1] && $c === $modules[$row + 1][$col] && $c === $modules[$row + 1][$col + 1]) {
                    $penalty += 3;
                }
            }
        }

        return $penalty;
    }

    private function renderPng(array $modules): string
    {
        $moduleCount = count($modules);
        $margin = 4;
        $moduleSize = 10;
        $pngSize = ($moduleCount + 2 * $margin) * $moduleSize;

        $image = imagecreatetruecolor($pngSize, $pngSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                if ($modules[$row][$col]) {
                    $x = ($col + $margin) * $moduleSize;
                    $y = ($row + $margin) * $moduleSize;
                    imagefilledrectangle($image, $x, $y, $x + $moduleSize - 1, $y + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
