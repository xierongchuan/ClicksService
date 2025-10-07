<?php

namespace App\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;

class ClickDto implements Arrayable
{
    public function __construct(
        public readonly string $clickId,
        public readonly int $offerId,
        public readonly string $source,
        public readonly int $timestamp,
        public readonly string $signature
    ) {
        //
    }

    public function toArray(): array
    {
        return [
            $this->clickId,
            $this->offerId,
            $this->source,
            $this->timestamp
        ];
    }
}
