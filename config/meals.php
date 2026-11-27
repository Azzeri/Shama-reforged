<?php

return [
    /*
     * Servings one planned meal uses up. One serving = one person and the
     * household eats together, so a meal in the plan is two servings: a
     * recipe for 6 cooked whole covers the cooking day + 2 leftover days.
     */
    'servings_per_meal' => (int) env('MEALS_SERVINGS_PER_MEAL', 2),

    /*
     * Week review ("Ocena tygodnia"): the 80/20 goal and the thresholds of
     * the "worth improving" cards.
     */
    'review' => [
        // At least this share of the rated meals should be healthy…
        'healthy_min_percent' => 80,
        // …and at most this share unhealthy.
        'unhealthy_max_percent' => 20,
        // Fewer rated meals than this: numbers are shown, the goal is not judged.
        'min_meals' => 5,
        // The same recipe on this many days in a row gets a card. A recipe for
        // 6 cooked whole is 3 days by design, hence 4.
        'streak_days' => 4,
        // "Long time no see": not in the plan for more than this many days.
        'long_ago_days' => 30,
    ],
];
