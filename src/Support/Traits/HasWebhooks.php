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
        if (method_exists(new static, 'restore')) {
            static::restored(function ($model) {
                $model->callWebhooks(self::$restoredEvent);
            });
        }
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
        if (!$this->webhooksTableExists()) {
            return;
        }

        $webhooks = $this->getWebhooksToCall($event);

        foreach ($webhooks as $webhook) {
            if ($this->isWehbookCallAuthorized($webhook)) {
                $this->callWebhookUrl($webhook);
            }
        }
    }

    protected function webhooksTableExists()
    {
        return Schema::hasTable((new Webhook)->table);
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
        if (!$this->module) {
            return [];
        }

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
                'json' => $this->getFormattedDataToDisplay()
            ]);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }

        return $response;
    }

    /**
     * Adds formatted record's data to display
     *
     * @param mixed $record
     * @return mixed
     */
    protected function getFormattedDataToDisplay()
    {
        if (empty($this->module)) {
            return $this->toJson();
        }

        $module = $this->module;

        $record = clone $this;

        foreach ($module->fields as $field) {
            $uitype = uitype($field->uitype_id);

            // If field name is not defined, it could be because the coloumn name is different.
            // Adds field name as a key of the record
            if ($uitype->name !== 'entity' && !$record->getAttributeValue($field->name) && $field->column !== $field->name) {
                $record->setAttribute($field->name, $record->getAttributeValue($field->column));
            }

            // If a special template exists, add it.
            $formattedValue = $uitype->getFormattedValueToDisplay($field, $record);
            if ($formattedValue && $formattedValue !== $record->getAttributeValue($field->name)) {
                $record->setAttribute($field->name.'_formatted', $formattedValue);
            }
        }

        // Had systematicaly deleted_at for record using soft delete
        if (!$record->getAttributeValue('deleted_at') && Schema::hasColumn($this->getTable(), 'deleted_at')) {
            $record->deleted_at = null;
        }

        return $record->toJson();
    }
}
