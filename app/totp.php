<?php

/**
 * Pure Native RFC 6238 TOTP (Time-Based One-Time Password) & Base32 Engine.
 * 100% Native PHP 8.1+, Zero external packages, Zero GD/Imagick dependencies.
 * Compliant with Google Authenticator, Authy, Apple Passwords, and 1Password.
 */

declare(strict_types=1);

/**
 * Encode arbitrary binary data into RFC 4648 Base32 string (A-Z, 2-7).
 */
function base32Encode(string $data): string
{
    if ($data === '') {
        return '';
    }
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }

    $out = '';
    $chunks = str_split($binary, 5);
    foreach ($chunks as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }

    $padLen = (8 - (strlen($out) % 8)) % 8;
    return $out . str_repeat('=', $padLen);
}

/**
 * Decode RFC 4648 Base32 string into binary data.
 */
function base32Decode(string $base32): string
{
    $clean = strtoupper(rtrim(trim($base32), '='));
    if ($clean === '') {
        return '';
    }
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $len = strlen($clean);
    for ($i = 0; $i < $len; $i++) {
        $pos = strpos($alphabet, $clean[$i]);
        if ($pos === false) {
            continue; // Tolerant of whitespace or invalid chars
        }
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $out = '';
    $bytes = str_split($binary, 8);
    foreach ($bytes as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/**
 * Generate cryptographically secure random Base32 secret for TOTP.
 */
function totpGenerateSecret(int $bytes = 20): string
{
    return rtrim(base32Encode(random_bytes($bytes)), '=');
}

/**
 * Compute RFC 6238 TOTP 6-digit code for a given timestamp.
 */
function totpCompute(string $secretBase32, ?int $timestamp = null, int $timeStep = 30, int $digits = 6): string
{
    $secretBinary = base32Decode($secretBase32);
    $time = $timestamp ?? time();
    $timeSlice = intdiv($time, $timeStep);

    $packedTime = pack('J', $timeSlice); // 64-bit unsigned big-endian
    $hash = hash_hmac('sha1', $packedTime, $secretBinary, true);

    $offset = ord($hash[19]) & 0x0F;
    $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);

    $otp = $binaryCode % (10 ** $digits);
    return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verify a TOTP code with time-drift skew tolerance (default window = 1 => +-30s).
 */
function totpVerify(string $secretBase32, string $code, int $window = 1, ?int $timestamp = null): bool
{
    $code = preg_replace('/\s+/', '', trim($code)) ?? '';
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }

    $time = $timestamp ?? time();
    for ($drift = -$window; $drift <= $window; $drift++) {
        $checkTime = $time + ($drift * 30);
        $validCode = totpCompute($secretBase32, $checkTime);
        if (hash_equals($validCode, $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Generate emergency backup recovery scratch codes.
 *
 * @return array{plaintext: array<int, string>, hashed: array<int, string>}
 */
function totpGenerateBackupCodes(int $count = 10, int $length = 8): array
{
    $plain = [];
    $hashed = [];
    for ($i = 0; $i < $count; $i++) {
        $bytes = random_bytes((int) ceil($length / 2));
        $code = substr(bin2hex($bytes), 0, $length);
        $plain[] = $code;
        $hashed[] = password_hash($code, PASSWORD_DEFAULT);
    }
    return [
        'plaintext' => $plain,
        'hashed' => $hashed,
    ];
}

/**
 * Verify and consume a single-use backup recovery code.
 *
 * @param array<int, string> $hashedCodes
 */
function totpVerifyBackupCode(string $code, array &$hashedCodes): bool
{
    $cleanCode = strtolower(trim($code));
    if ($cleanCode === '') {
        return false;
    }

    foreach ($hashedCodes as $idx => $hash) {
        if (password_verify($cleanCode, $hash)) {
            unset($hashedCodes[$idx]);
            $hashedCodes = array_values($hashedCodes);
            return true;
        }
    }
    return false;
}

/**
 * Generate standard otpauth:// URI for authenticator apps.
 */
function totpGetProvisioningUri(string $secretBase32, string $accountName, string $issuer = 'PowerDNS Admin'): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
    $params = http_build_query([
        'secret' => rtrim($secretBase32, '='),
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return 'otpauth://totp/' . $label . '?' . $params;
}

/**
 * Lightweight Pure PHP QR Code Matrix Generator (ISO/IEC 18004 Model 2).
 * Renders standalone clean vector SVG markup without GD or external libraries.
 */
class NativeQrSvg
{
    /**
     * @var array<int, int>
     */
    private static array $exp = [];

    /**
     * @var array<int, int>
     */
    private static array $log = [];

    private static bool $gfReady = false;

    private static function initGf(): void
    {
        if (self::$gfReady) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$gfReady = true;
    }

    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        self::initGf();
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    /**
     * @return array<int, int>
     */
    private static function rsPoly(int $eccCount): array
    {
        self::initGf();
        $poly = [1];
        for ($i = 0; $i < $eccCount; $i++) {
            $t = [];
            for ($j = 0; $j < count($poly); $j++) {
                $t[$j + 1] = $poly[$j];
            }
            $t[0] = 0;
            $root = self::$exp[$i];
            for ($j = 0; $j < count($poly); $j++) {
                $t[$j] ^= self::gfMul($poly[$j], $root);
            }
            $poly = $t;
        }
        return $poly;
    }

    /**
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private static function rsCalc(array $data, int $eccCount): array
    {
        $poly = self::rsPoly($eccCount);
        $result = array_fill(0, $eccCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $eccCount; $i++) {
                $result[$i] ^= self::gfMul($poly[$i + 1], $factor);
            }
        }
        return $result;
    }

    /**
     * @var array<int, array<int, int>>
     */
    private const FINDER_PATTERN = [
        [1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 1, 1, 1, 1, 1, 1],
    ];

    /**
     * @var array<int, array<int, int>>
     */
    private const ALIGN_PATTERN = [
        [1, 1, 1, 1, 1],
        [1, 0, 0, 0, 1],
        [1, 0, 1, 0, 1],
        [1, 0, 0, 0, 1],
        [1, 1, 1, 1, 1],
    ];

    /**
     * Encode text into QR data codewords and compute Reed-Solomon ECC.
     */
    private static function encodeDataCodewords(string $text, int &$ver, int &$gridSize): string
    {
        $len = strlen($text);
        $ver = 4;
        $totalDataBytes = 64; // V4-L has 80 total codewords, 16 EC words -> 64 data words
        $eccWords = 16;
        $gridSize = 33;
        if ($len > 60) {
            $ver = 5;
            $totalDataBytes = 86; // V5-L has 108 total codewords, 22 EC words -> 86 data words
            $eccWords = 22;
            $gridSize = 37;
        }

        // Build data bitstream: Mode 0100 (Byte), 8-bit length
        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }
        $bits .= '0000'; // Terminator
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }
        $bytes = [];
        $chunks = str_split($bits, 8);
        foreach ($chunks as $chunk) {
            $bytes[] = bindec($chunk);
        }

        // Pad to capacity
        $pad = [0xEC, 0x11];
        $padIdx = 0;
        while (count($bytes) < $totalDataBytes) {
            $bytes[] = $pad[$padIdx % 2];
            $padIdx++;
        }

        // Calculate Reed-Solomon Error Correction Codewords
        $ecc = self::rsCalc($bytes, $eccWords);
        $allCodewords = array_merge($bytes, $ecc);

        // Build codeword bitstream
        $allBits = '';
        foreach ($allCodewords as $cw) {
            $allBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        return $allBits;
    }

    /**
     * Place 7x7 finder pattern with 1-module white separator.
     *
     * @param array<int, array<int, int|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     */
    private static function placeFinder(array &$matrix, array &$reserved, int $r, int $c, int $gridSize): void
    {
        for ($i = -1; $i <= 7; $i++) {
            for ($j = -1; $j <= 7; $j++) {
                $row = $r + $i;
                $col = $c + $j;
                if ($row >= 0 && $row < $gridSize && $col >= 0 && $col < $gridSize) {
                    $isFinder = ($i >= 0 && $i < 7 && $j >= 0 && $j < 7);
                    $matrix[$row][$col] = $isFinder ? self::FINDER_PATTERN[$i][$j] : 0;
                    $reserved[$row][$col] = true;
                }
            }
        }
    }

    /**
     * Place timing patterns, dark module, and format info reservations.
     *
     * @param array<int, array<int, int|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     */
    private static function placeTimingAndFormat(array &$matrix, array &$reserved, int $gridSize): void
    {
        for ($i = 8; $i < $gridSize - 8; $i++) {
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
                $reserved[$i][6] = true;
            }
        }

        // Dark module
        $matrix[$gridSize - 8][8] = 1;
        $reserved[$gridSize - 8][8] = true;

        // Reserve format info areas
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$gridSize - 1 - $i] = true;
            $reserved[$gridSize - 1 - $i][8] = true;
        }
    }

    /**
     * Place 5x5 alignment pattern.
     *
     * @param array<int, array<int, int|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     */
    private static function placeAlignment(array &$matrix, array &$reserved, int $pos): void
    {
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                $r = $pos + $i;
                $c = $pos + $j;
                if (!$reserved[$r][$c]) {
                    $matrix[$r][$c] = self::ALIGN_PATTERN[$i + 2][$j + 2];
                    $reserved[$r][$c] = true;
                }
            }
        }
    }

    /**
     * Set a single masked data bit in the QR matrix if the cell is unreserved.
     *
     * @param array<int, array<int, int|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     * @param array{0: int, 1: int} $pos
     */
    private static function setCellBit(
        array &$matrix,
        array $reserved,
        array $pos,
        string $allBits,
        int &$bitIdx,
        int $totalBits
    ): void {
        [$row, $col] = $pos;
        if ($reserved[$row][$col]) {
            return;
        }
        $bit = ($bitIdx < $totalBits) ? (int) $allBits[$bitIdx] : 0;
        $mask = (($row + $col) % 2 === 0) ? 1 : 0;
        $matrix[$row][$col] = $bit ^ $mask;
        $bitIdx++;
    }

    /**
     * Process a 2-column vertical stripe in zig-zag order.
     *
     * @param array<int, array<int, int|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     * @param array{col: int, dir: int, size: int} $stripe
     */
    private static function processColumnStripe(
        array &$matrix,
        array $reserved,
        array $stripe,
        string $allBits,
        int &$bitIdx,
        int $totalBits
    ): void {
        $dir = $stripe['dir'];
        $gridSize = $stripe['size'];
        $col = $stripe['col'];
        $rowStart = ($dir === -1) ? ($gridSize - 1) : 0;
        $rowEnd = ($dir === -1) ? -1 : $gridSize;
        $step = ($dir === -1) ? -1 : 1;

        for ($row = $rowStart; $row !== $rowEnd; $row += $step) {
            self::setCellBit($matrix, $reserved, [$row, $col], $allBits, $bitIdx, $totalBits);
            self::setCellBit($matrix, $reserved, [$row, $col - 1], $allBits, $bitIdx, $totalBits);
        }
    }

    /**
     * Fill encoded data bits into matrix in 2-column zig-zag traversal.
     *
     * @param array<int, array<int, int|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     */
    private static function fillDataBits(array &$matrix, array $reserved, string $allBits, int $gridSize): void
    {
        $bitIdx = 0;
        $totalBits = strlen($allBits);
        $dir = -1; // up
        $col = $gridSize - 1;
        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }
            self::processColumnStripe(
                $matrix,
                $reserved,
                ['col' => $col, 'dir' => $dir, 'size' => $gridSize],
                $allBits,
                $bitIdx,
                $totalBits
            );
            $dir = -$dir;
            $col -= 2;
        }
    }

    /**
     * Apply 15-bit format information for Level L, Mask 0 (0x77C4).
     *
     * @param array<int, array<int, int|null>> $matrix
     */
    private static function applyFormatInfo(array &$matrix, int $gridSize): void
    {
        $fmtBits = '111011111000100';
        $fmtIdx = 0;
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $matrix[8][$i] = (int) $fmtBits[$fmtIdx];
                $fmtIdx++;
            }
        }
        for ($i = 7; $i >= 0; $i--) {
            if ($i !== 6) {
                $matrix[$i][8] = (int) $fmtBits[$fmtIdx];
                $fmtIdx++;
            }
        }
        for ($i = 0; $i < 7; $i++) {
            $matrix[$gridSize - 1 - $i][8] = (int) $fmtBits[$i];
        }
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$gridSize - 8 + $i] = (int) $fmtBits[7 + $i];
        }
    }

    /**
     * Render matrix into crisp vector SVG markup.
     *
     * @param array<int, array<int, int|null>> $matrix
     */
    private static function renderSvgMarkup(array $matrix, int $gridSize, int $displaySize): string
    {
        $quiet = 4;
        $fullSize = $gridSize + ($quiet * 2);
        $paths = [];
        for ($r = 0; $r < $gridSize; $r++) {
            for ($c = 0; $c < $gridSize; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $quiet;
                    $y = $r + $quiet;
                    $paths[] = "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        $pathData = implode('', $paths);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $fullSize . ' ' . $fullSize . '" '
            . 'width="' . $displaySize . '" height="' . $displaySize . '" shape-rendering="crispEdges" '
            . 'role="img" aria-label="QR Code TOTP">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path d="' . $pathData . '" fill="#000000"/>'
            . '</svg>';
    }

    /**
     * Render QR code as vector SVG markup.
     */
    public static function render(string $text, int $displaySize = 220): string
    {
        $ver = 4;
        $gridSize = 33;
        $allBits = self::encodeDataCodewords($text, $ver, $gridSize);

        $matrix = array_fill(0, $gridSize, array_fill(0, $gridSize, null));
        $reserved = array_fill(0, $gridSize, array_fill(0, $gridSize, false));

        self::placeFinder($matrix, $reserved, 0, 0, $gridSize);
        self::placeFinder($matrix, $reserved, 0, $gridSize - 7, $gridSize);
        self::placeFinder($matrix, $reserved, $gridSize - 7, 0, $gridSize);

        self::placeTimingAndFormat($matrix, $reserved, $gridSize);
        self::placeAlignment($matrix, $reserved, ($ver === 4) ? 26 : 30);

        self::fillDataBits($matrix, $reserved, $allBits, $gridSize);
        self::applyFormatInfo($matrix, $gridSize);

        return self::renderSvgMarkup($matrix, $gridSize, $displaySize);
    }
}

/**
 * Render vector SVG QR code for TOTP URI.
 */
function totpGenerateQrSvg(string $provisioningUri, int $size = 220): string
{
    return NativeQrSvg::render($provisioningUri, $size);
}
