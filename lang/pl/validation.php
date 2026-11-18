<?php

/*
 * Polish validation messages for the rules the app actually uses. Anything
 * missing here falls back to the English framework message.
 */
return [
    'accepted' => 'Pole :attribute musi zostać zaakceptowane.',
    'after_or_equal' => 'Pole :attribute musi być datą równą lub późniejszą niż :date.',
    'array' => 'Pole :attribute musi być listą.',
    'confirmed' => 'Pole :attribute nie zgadza się z potwierdzeniem.',
    'current_password' => 'Hasło jest nieprawidłowe.',
    'date' => 'Pole :attribute musi być poprawną datą.',
    'date_format' => 'Pole :attribute musi mieć format :format.',
    'distinct' => 'Pole :attribute ma zduplikowaną wartość.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'exists' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'gt' => [
        'numeric' => 'Pole :attribute musi być większe niż :value.',
    ],
    'in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'lowercase' => 'Pole :attribute musi być zapisane małymi literami.',
    'max' => [
        'array' => 'Pole :attribute może mieć najwyżej :max elementów.',
        'numeric' => 'Pole :attribute nie może być większe niż :max.',
        'string' => 'Pole :attribute może mieć najwyżej :max znaków.',
    ],
    'min' => [
        'array' => 'Pole :attribute musi mieć co najmniej :min elementów.',
        'numeric' => 'Pole :attribute musi wynosić co najmniej :min.',
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
    ],
    'numeric' => 'Pole :attribute musi być liczbą.',
    'password' => [
        'letters' => 'Pole :attribute musi zawierać co najmniej jedną literę.',
        'mixed' => 'Pole :attribute musi zawierać małe i wielkie litery.',
        'numbers' => 'Pole :attribute musi zawierać co najmniej jedną cyfrę.',
        'symbols' => 'Pole :attribute musi zawierać co najmniej jeden znak specjalny.',
        'uncompromised' => 'Podane hasło wyciekło w sieci. Wybierz inne.',
    ],
    'present' => 'Pole :attribute musi być obecne.',
    'required' => 'Pole :attribute jest wymagane.',
    'string' => 'Pole :attribute musi być tekstem.',
    'unique' => 'Taka wartość pola :attribute już istnieje.',
    'url' => 'Pole :attribute musi być poprawnym adresem URL.',

    'attributes' => [
        'name' => 'imię',
        'email' => 'e-mail',
        'password' => 'hasło',
        'password_confirmation' => 'potwierdzenie hasła',
        'current_password' => 'obecne hasło',
        'code' => 'kod',
        'recovery_code' => 'kod odzyskiwania',
        'recipeName' => 'nazwa',
        'recipeUrl' => 'źródło',
        'editIngredientName' => 'nazwa składnika',
        'newIngredientName' => 'nazwa składnika',
        'newItemName' => 'nazwa produktu',
        'editName' => 'nazwa produktu',
        'historyTo' => 'historia do',
        'importJson' => 'odpowiedź AI',
    ],
];
