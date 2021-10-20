<?php

namespace Tests\Api;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tests\DataFile;
use Uploadcare\Configuration;
use Uploadcare\Exception\HttpException;
use Uploadcare\Exception\InvalidArgumentException;
use Uploadcare\File\File;
use Uploadcare\Interfaces\File\FileInfoInterface;
use Uploadcare\Uploader\Uploader;

class UploaderEdgeCasesTest extends TestCase
{
    protected function getConfiguration()
    {
        return Configuration::create('public-key', 'private-key');
    }

    /**
     * @param array $methods
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Uploader
     */
    protected function getMockUploader($methods = [])
    {
        return $this->getMockBuilder(Uploader::class)
            ->setConstructorArgs([$this->getConfiguration()])
            ->setMethods($methods)
            ->getMock();
    }

    /** @noinspection PhpParamsInspection */
    public function testWrongResource()
    {
        $this->expectException(InvalidArgumentException::class);
        $uploader = new Uploader($this->getConfiguration());
        $uploader->fromResource('not-a-resource');
        $this->expectExceptionMessageRegExp('Wrong parameter at');
    }

    public function testSwitchToMultipart()
    {
        $mock = $this->getMockUploader(['getSize', 'sendRequest', 'fileInfo']);
        $mock
            ->expects(self::once())
            ->method('getSize')
            ->withAnyParameters()
            ->willReturn(Uploader::MULTIPART_UPLOAD_SIZE + 1000);
        $mock
            ->expects(self::once())
            ->method('fileInfo')
            ->withAnyParameters()
            ->willReturn(new File());
        $mock
            ->expects(self::atLeastOnce())
            ->method('sendRequest')
            ->withAnyParameters()
            ->willReturn(new Response(200, [], DataFile::contents('file-info-api-response.json')));

        $handle = \fopen(\dirname(__DIR__) . '/_data/empty.file.txt', 'rb');
        self::assertInstanceOf(FileInfoInterface::class, $mock->fromResource($handle));
    }

    public function testHttpExceptionInDirectUpload()
    {
        $this->expectException(HttpException::class);

        $mock = $this->getMockUploader(['sendRequest']);
        $mock
            ->expects(self::once())
            ->method('sendRequest')
            ->willThrowException(new ConnectException('Unable to connect', new Request('GET', 'some')));

        $handle = \fopen(\dirname(__DIR__) . '/_data/empty.file.txt', 'rb');
        $mock->fromResource($handle);
        $this->expectExceptionMessageRegExp('Unable to connect');
    }
}
