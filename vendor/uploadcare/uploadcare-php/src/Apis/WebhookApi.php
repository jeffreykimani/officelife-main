<?php declare(strict_types=1);

namespace Uploadcare\Apis;

use Uploadcare\Interfaces\Api\WebhookApiInterface;
use Uploadcare\Interfaces\File\CollectionInterface;
use Uploadcare\Interfaces\Response\WebhookInterface;
use Uploadcare\Response\WebhookCollection;
use Uploadcare\Response\WebhookResponse as Webhook;
use Uploadcare\Webhook as WebhookDecorator;
use Uploadcare\WebhookCollection as WebhookCollectionDecorator;

final class WebhookApi extends AbstractApi implements WebhookApiInterface
{
    /**
     * {@inheritDoc}
     */
    public function listWebhooks(): CollectionInterface
    {
        $response = $this->request('GET', 'webhooks/');
        $webhooks = $this->configuration->getSerializer()
            ->deserialize($response->getBody()->getContents());

        $result = new WebhookCollection();
        foreach ($webhooks as $webhook) {
            $obj = $this->configuration->getSerializer()
                ->deserialize(\json_encode($webhook), Webhook::class);

            if ($obj instanceof Webhook) {
                $result->add($obj);
            }
        }

        return new WebhookCollectionDecorator($result, $this);
    }

    /**
     * {@inheritDoc}
     */
    public function createWebhook(string $targetUrl, bool $isActive = true, string $event = 'file.uploaded'): WebhookInterface
    {
        $response = $this->request('POST', 'webhooks/', [
            'form_params' => [
                'target_url' => $targetUrl,
                'event' => $event,
                'is_active' => $isActive,
            ],
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $webhook = $this->configuration->getSerializer()
            ->deserialize($response->getBody()->getContents(), Webhook::class);

        if (!$webhook instanceof WebhookInterface) {
            throw new \RuntimeException('Unable to deserialize response. Call to support');
        }

        return new WebhookDecorator($webhook, $this);
    }

    /**
     * {@inheritDoc}
     */
    public function updateWebhook(int $id, array $parameters): WebhookInterface
    {
        $uri = \sprintf('webhooks/%s/', $id);
        $formData = [];
        if (isset($parameters['target_url'])) {
            $formData['target_url'] = (string) $parameters['target_url'];
        }
        if (isset($parameters['event'])) {
            $formData['event'] = (string) $parameters['event'];
        }
        if (isset($parameters['is_active'])) {
            $formData['is_active'] = (bool) $parameters['is_active'];
        }

        $response = $this->request('PUT', $uri, [
            'form_params' => $formData,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $result = $this->configuration->getSerializer()
            ->deserialize($response->getBody()->getContents(), Webhook::class);

        if (!$result instanceof WebhookInterface) {
            throw new \RuntimeException('Unable to deserialize response. Call to support');
        }

        return new WebhookDecorator($result, $this);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteWebhook(string $targetUrl): bool
    {
        $response = $this->request('DELETE', 'webhooks/unsubscribe/', [
            'form_params' => ['target_url' => $targetUrl],
        ]);

        return $response->getStatusCode() === 204;
    }
}
