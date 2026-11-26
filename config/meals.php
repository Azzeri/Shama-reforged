<?php

return [
    /*
     * Servings one planned meal uses up. One serving = one person and the
     * household eats together, so a meal in the plan is two servings: a
     * recipe for 6 cooked whole covers the cooking day + 2 leftover days.
     */
    'servings_per_meal' => (int) env('MEALS_SERVINGS_PER_MEAL', 2),
];
