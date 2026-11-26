<?php

namespace App\Support\RecipeImport;

use RuntimeException;

/** reason: url (not an http(s) address we may fetch), fetch (network / size), parse (no recipe on the page) */
class RecipeImportFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Recipe import failed: {$reason}");
    }
}
