<?php

namespace Uccello\Webhook\Support\Traits;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Uccello\Webhook\Models\Webhook;

trait HasWebhooks
{
    /**
     * Created event
     *
     * @var string
     */
    protected static $createdEvent = 'created';

    /**
     * Updated event
     *
     * @var string
     */
    protected static $updatedEvent = 'updated';

    /**
     * Deleted event
     *
     * @var string
     */
    protected static $deletedEvent = 'deleted';

    /**
     * Restored event
     *
     * @var string
     */
    protected static $restoredEvent = 'restored';

    /**
     * Boot
     *
     * @return void
     */
    protected static function bootHasWebhooks()
    {
        // Created
        static::created(function ($model) {
            $model->callWebhooks(self::$createdEvent);
        });

        // Updated
        static::updated(function ($model) {
            $model->callWebhooks(self::$updatedEvent);
        });

        // Deleted
        static::deleted(function ($model) {
            $model->callWebhooks(self::$deletedEvent);
        });

        // Restored
        static::restored(function ($model) {
            $model->callWebhooks(self::$restoredEvent);
        });
    }

    /**
     * Calls all webhooks callable for the current module and the asked event type.
     *
     * @param string $event
     *
     * @return void
     */
    public function callWebhooks($event)
    {
        $webhooks = $this->getWebhooksToCall($event);

        foreach ($webhooks as $webhook) {
            if ($this->isWehbookCallAuthorized($webhook)) {
                $this->callWebhookUrl($webhook);
            }
        }
    }

    /**
     * Returns all webhooks callable for the current module and the asked event type.
     *
     * @param string $event
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getWebhooksToCall($event)
    {
        // Get all compatible webhooks
        $query = Webhook::where('rel_module_id', $this->module->id)
                ->where('event', $event);

        // Filter on domain if domain_id column exists
        if (Schema::hasColumn($this->getTable(), 'domain_id')) {
            $query->where('domain_id', $this->domain_id);
        }

        return $query->get();
    }

    /**
     * Checks if it is authorized to call a webhook.
     * You can override this function to disallow some users for example.
     *
     * @param \Uccello\Webhook\Models\Webhook $webhook
     *
     * @return boolean
     */
    protected function isWehbookCallAuthorized(Webhook $webhook)
    {
        return true;
    }

    /**
     * Calls a webhook and returns the response.
     *
     * @return void
     */
    protected function callWebhookUrl(Webhook $webhook)
    {
        $response = "";

        try {
            $client = new Client();
            $response = $client->request('POST', $webhook->url, [
                'json' => $this->toJson()
            ]);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }

        return $response;
    }
}
