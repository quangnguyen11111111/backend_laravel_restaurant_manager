<?php

namespace App\Patterns\Observer\Contracts;

interface Observer
{
    /**
     * @param Subject $subject
     * @param string $event
     */
    public function update(Subject $subject, string $event = ''): void;
}
