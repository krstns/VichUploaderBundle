<?php

namespace Vich\UploaderBundle\Tests\Form\Type;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyPath;
use Vich\TestBundle\Entity\Product;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

class VichFileTypeTest extends TestCase
{
    protected const TESTED_TYPE = VichFileType::class;

    /**
     * @dataProvider configureOptionsBCDataProvider
     * @group legacy
     * @expectedDeprecation The "download_link" option is deprecated since version 1.6 and will be removed in 2.0. You should use "download_uri" instead.
     */
    public function testConfigureOptionsBC(array $options, array $resolvedOptions): void
    {
        $optionsResolver = new OptionsResolver();

        $storage = $this->createMock(StorageInterface::class);
        $uploadHandler = $this->createMock(UploadHandler::class);
        $propertyMappingFactory = $this->createMock(PropertyMappingFactory::class);
        $propertyAccessor = $this->createMock(PropertyAccessor::class);

        $testedType = static::TESTED_TYPE;

        $type = new $testedType($storage, $uploadHandler, $propertyMappingFactory, $propertyAccessor);

        $type->configureOptions($optionsResolver);

        $resolved = $optionsResolver->resolve($options);

        foreach ($resolvedOptions as $key => $value) {
            self::assertArrayHasKey($key, $resolved);
            self::assertSame($value, $resolved[$key]);
        }
    }

    public function configureOptionsBCDataProvider(): array
    {
        return [
            [['download_link' => true], ['download_uri' => true]],
            [['download_link' => false], ['download_uri' => false]],
        ];
    }

    public function testEmptyDownloadLinkDoNotThrowsDeprecation(): void
    {
        $optionsResolver = new OptionsResolver();

        $storage = $this->createMock(StorageInterface::class);
        $uploadHandler = $this->createMock(UploadHandler::class);
        $propertyMappingFactory = $this->createMock(PropertyMappingFactory::class);
        $propertyAccessor = $this->createMock(PropertyAccessor::class);

        $testedType = static::TESTED_TYPE;

        $type = new $testedType($storage, $uploadHandler, $propertyMappingFactory, $propertyAccessor);

        $type->configureOptions($optionsResolver);

        $resolved = $optionsResolver->resolve([]);

        foreach (['download_uri' => true, 'download_link' => null] as $key => $value) {
            self::assertArrayHasKey($key, $resolved);
            self::assertSame($value, $resolved[$key]);
        }
    }

    /**
     * @dataProvider buildViewDataProvider
     */
    public function testBuildView(Product $object = null, array $options, array $vars): void
    {
        $field = 'image';

        $storage = $this->createMock(StorageInterface::class);
        $storage
            ->expects($this->any())
            ->method('resolveUri')
            ->with($object, $field)
            ->willReturn('resolved-uri');

        $parentForm = $this->createMock(FormInterface::class);
        $parentForm
            ->expects($this->any())
            ->method('getData')
            ->willReturn($object);

        $config = $this->createMock(FormConfigInterface::class);
        $config
            ->expects($this->any())
            ->method('getOption')
            ->willReturnCallback(function (string $key) use ($options) {
                return $options[$key] ?? null;
            });

        $form = $this->createMock(FormInterface::class);
        $form
            ->expects($this->any())
            ->method('getParent')
            ->willReturn($parentForm);
        $form
            ->expects($this->any())
            ->method('getName')
            ->willReturn($field);
        $form
            ->expects($this->any())
            ->method('getConfig')
            ->willReturn($config);

        $uploadHandler = $this->createMock(UploadHandler::class);
        $propertyMappingFactory = $this->createMock(PropertyMappingFactory::class);

        $propertyAccessor = $this->createMock(PropertyAccessor::class);

        if (isset($options['download_label'])) {
            if (true === $options['download_label']) {
                $mapping = $this->createMock(PropertyMapping::class);
                $mapping
                    ->expects(self::once())
                    ->method('readProperty')
                    ->with($object, 'originalName')
                    ->willReturn($object->getImageOriginalName());

                $propertyMappingFactory
                    ->expects(self::once())
                    ->method('fromField')
                    ->with($object, $field)
                    ->willReturn($mapping);
            }

            if ($options['download_label'] instanceof PropertyPath) {
                $propertyAccessor
                    ->expects(self::once())
                    ->method('getValue')
                    ->with($object, $options['download_label'])
                    ->willReturn($object->getTitle());
            }
        }

        $testedType = static::TESTED_TYPE;

        $view = new FormView();
        $type = new $testedType($storage, $uploadHandler, $propertyMappingFactory, $propertyAccessor);
        $type->buildView($view, $form, $options);
        self::assertEquals($vars, $view->vars);
    }

    public function buildViewDataProvider(): array
    {
        $object = new Product();
        $object->setImageOriginalName('image.jpeg');
        $object->setTitle('Product1');

        return [
            [
                $object,
                [
                    'download_label' => 'custom label',
                    'download_uri' => true,
                    'asset_helper' => true,
                ],
                [
                    'object' => $object,
                    'download_label' => 'custom label',
                    'download_uri' => 'resolved-uri',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => true,
                ],
            ],
            [
                null,
                [
                    'download_label' => 'download',
                    'download_uri' => false,
                    'asset_helper' => false,
                ],
                [
                    'object' => null,
                    'download_uri' => null,
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => 'download',
                    'download_uri' => false,
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_uri' => null,
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => 'download',
                    'download_uri' => static function (Product $product) {
                        return '/download/'.$product->getImageOriginalName();
                    },
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_label' => 'download',
                    'download_uri' => '/download/image.jpeg',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => 'download',
                    'download_uri' => 'custom-uri',
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_label' => 'download',
                    'download_uri' => 'custom-uri',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => true,
                    'download_uri' => true,
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_label' => 'image.jpeg',
                    'translation_domain' => false,
                    'download_uri' => 'resolved-uri',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => static function (Product $product) {
                        return 'prefix-'.$product->getImageOriginalName();
                    },
                    'download_uri' => true,
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_label' => 'prefix-image.jpeg',
                    'translation_domain' => false,
                    'download_uri' => 'resolved-uri',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => static function (Product $product) {
                        return [
                            'download_label' => 'prefix-'.$product->getImageOriginalName(),
                            'translation_domain' => 'messages',
                        ];
                    },
                    'download_uri' => true,
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_label' => 'prefix-image.jpeg',
                    'translation_domain' => 'messages',
                    'download_uri' => 'resolved-uri',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
            [
                $object,
                [
                    'download_label' => new PropertyPath('title'),
                    'download_uri' => true,
                    'asset_helper' => false,
                ],
                [
                    'object' => $object,
                    'download_label' => $object->getTitle(),
                    'translation_domain' => false,
                    'download_uri' => 'resolved-uri',
                    'value' => null,
                    'attr' => [],
                    'asset_helper' => false,
                ],
            ],
        ];
    }
}
