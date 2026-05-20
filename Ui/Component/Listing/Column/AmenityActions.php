<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class AmenityActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $amenityId = (int) ($item['amenity_id'] ?? 0);
            if ($amenityId <= 0) {
                continue;
            }
            $item[$name] = [
                'edit' => [
                    'href'  => $this->urlBuilder->getUrl('etechflow_isp/amenity/edit', ['amenity_id' => $amenityId]),
                    'label' => (string) __('Edit'),
                ],
                'delete' => [
                    'href'    => $this->urlBuilder->getUrl('etechflow_isp/amenity/delete', ['amenity_id' => $amenityId]),
                    'label'   => (string) __('Delete'),
                    'confirm' => [
                        'title'   => (string) __('Delete %1', $item['label'] ?? $amenityId),
                        'message' => (string) __('Delete this amenity? Store-amenity assignments will be removed too.'),
                    ],
                ],
            ];
        }

        return $dataSource;
    }
}
