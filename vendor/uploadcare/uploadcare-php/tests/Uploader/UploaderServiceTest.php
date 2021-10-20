<?php

namespace Tests\Uploader;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Uploadcare\Configuration;
use Uploadcare\Exception\InvalidArgumentException;
use Uploadcare\Interfaces\File\FileInfoInterface;
use Uploadcare\Security\Signature;
use Uploadcare\Serializer\SerializerFactory;
use Uploadcare\Uploader\Uploader;

/**
 * @group broken
 */
class UploaderServiceTest extends TestCase
{
    /**
     * @param Configuration|null $configuration
     */
    private function getMockUploader(Configuration $configuration = null)
    {
        $uploader = $this->getMockBuilder(Uploader::class)
            ->setConstructorArgs([$configuration ?: $this->getConf()])
            ->getMock();

        $response = $this->getMockBuilder(FileInfoInterface::class);
        $uploader->expects(self::any())->method('fromResource')
            ->willReturn($response);

        return $uploader;
    }

    private function getConf(ClientInterface $client = null)
    {
        return new Configuration('demo-public-key', new Signature('demo-private-key'), $client ?: $this->mockClient(), $this->getSerializer());
    }

    /**
     * @param int   $status
     * @param array $headers
     * @param null  $body
     *
     * @return ClientInterface
     */
    private function mockClient($status = 200, array $headers = [], $body = null)
    {
        $mock = new MockHandler([
            new Response($status, $headers, $body),
        ]);

        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    private function getSerializer()
    {
        return SerializerFactory::create();
    }

    public function testCheckResourceException()
    {
        $this->expectException(InvalidArgumentException::class);

        $uploader = new Uploader($this->getConf());
        $checkResource = (new \ReflectionObject($uploader))->getMethod('checkResource');
        $checkResource->setAccessible(true);
        $checkResource->invokeArgs($uploader, ['string']);

        $this->expectExceptionMessageRegExp('Expected resource');
    }

    public function testCheckExistsButNotValidResource()
    {
        $this->expectException(\UnexpectedValueException::class);
        $uploader = new Uploader($this->getConf());
        $checkResource = (new \ReflectionObject($uploader))->getMethod('checkResource');
        $checkResource->setAccessible(true);
        $checkResource->invokeArgs($uploader, [\fopen(\dirname(__DIR__) . '/_data/empty.file.txt', 'wb')]);

        $this->expectExceptionMessageRegExp('metadata parameter can be');
    }

    public function testCheckResourceMetadataNotSet()
    {
        $this->expectException(\UnexpectedValueException::class);
        $uploader = new Uploader($this->getConf());
        $checkResourceMetadata = (new \ReflectionObject($uploader))->getMethod('checkResourceMetadata');
        $checkResourceMetadata->setAccessible(true);
        $metadata = [
            'foo' => 'bar',
            'wrapper_type' => 'some type',
        ];
        $checkResourceMetadata->invokeArgs($uploader, [$metadata]);

        $this->expectExceptionMessageRegExp('not exists in metadata');
    }

    public function testCheckInvalidMetadataSet()
    {
        $this->expectException(\UnexpectedValueException::class);
        $uploader = new Uploader($this->getConf());
        $checkResourceMetadata = (new \ReflectionObject($uploader))->getMethod('checkResourceMetadata');
        $checkResourceMetadata->setAccessible(true);

        $meta = [
            'wrapper_type' => 'not valid',
            'stream_type' => 'tcp_socket/ssl',
            'mode' => 'rb',
        ];
        $checkResourceMetadata->invokeArgs($uploader, [$meta]);

        $this->expectExceptionMessageRegExp('metadata parameter can be');
    }

    public function testMakeMultipartParameters()
    {
        $uploader = new Uploader($this->getConf());
        $reflection = new \ReflectionObject($uploader);
        $getDefaultParameters = $reflection->getMethod('getDefaultParameters');
        $getDefaultParameters->setAccessible(true);
        $defaults = $getDefaultParameters->invoke($uploader);

        $makeMultipartParameters = $reflection->getMethod('makeMultipartParameters');
        $makeMultipartParameters->setAccessible(true);

        $parameters = \array_merge($defaults, [
            ['name' => 'file', 'contents' => 'Hello, world'],
        ]);

        $result = $makeMultipartParameters->invokeArgs($uploader, [$parameters]);
        self::assertArrayHasKey('multipart', $result);
        foreach ($result['multipart'] as $item) {
            self::assertArrayHasKey('name', $item);
            self::assertArrayHasKey('contents', $item);
        }
    }

    public function testUploadFromFileIfFileNotExists()
    {
        $this->expectException(InvalidArgumentException::class);
        $uploader = new Uploader($this->getConf());
        $uploader->fromPath('/file/does/not/exists');
        $this->expectExceptionMessageRegExp('Unable to read');
    }

    public function testUploadFromNotExistsUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $url = 'http://host.does.hot.exists';
        (new Uploader($this->getConf()))->fromUrl($url);
        $this->expectExceptionMessageRegExp('Unable to open');
    }

    /**
     * @group local-only
     *
     * @throws \ReflectionException
     */
    public function testGetSizeMethod()
    {
        $path = \dirname(__DIR__) . '/_data/file-info.json';
        $size = \filesize($path);
        $handle = \fopen($path, 'rb');

        $uploader = new Uploader($this->getConf());
        $getSize = (new \ReflectionObject($uploader))->getMethod('getSize');
        $getSize->setAccessible(true);

        self::assertEquals($size, $getSize->invokeArgs($uploader, [$handle]));
        self::assertEquals(0, $getSize->invokeArgs($uploader, [\fopen('https://httpbin.org/encoding/utf8', 'rb')]));
    }
}
