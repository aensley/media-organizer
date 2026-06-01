<?php

if (!class_exists('getID3')) {
    class getID3
    {
        public static ?array $result = null;

        public function analyze(string $file): array
        {
            return self::$result ?? [
                'quicktime' => [
                    'timestamps_unix' => [
                        'create' => ['moov.mvhd' => mktime(0, 0, 0, 7, 5, 2016)],
                    ],
                ],
            ];
        }
    }
}
