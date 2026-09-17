<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Tests\Seo;

#[\AllowDynamicProperties]
final class SeoPageStub
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(private readonly string $path, array $properties = [])
    {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
