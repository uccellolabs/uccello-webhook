<?php

namespace Uccello\Webhook\Support\Interfaces;

interface CallWebhooks
{
    /**
     * Calls all webhooks callable for the current module and the asked event type.
     *
     * @param string $event
     *
     * @return void
     */
    public function callWebhooks($eventType);
}
