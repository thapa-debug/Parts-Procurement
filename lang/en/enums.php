<?php

return [

    // Shared across every portal (admin/vendor/buyer) that displays a
    // vendor response -- identical meaning regardless of who's viewing it,
    // unlike genuinely per-caller copy (see CONVENTIONS.md's reveal-
    // component note), so this lives in one place instead of being copied
    // into each portal's own lang file.
    'quality_rank' => [
        's' => 'S -- Like new / OEM equivalent',
        'a' => 'A -- Excellent condition',
        'b' => 'B -- Minor wear, may need light repair',
        'c' => 'C -- Junk / parts only',
    ],

    'lead_time' => [
        'within_2_days' => 'Same day to 2 days',
        'within_1_week' => '3 days to 1 week',
        'within_2_weeks' => 'Within 2 weeks',
        'undetermined' => 'Undetermined',
    ],

];
