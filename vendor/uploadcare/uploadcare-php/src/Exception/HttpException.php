<?php declare(strict_types=1);

namespace Uploadcare\Exception;

use Psr\Http\Message\RequestInterface;

class HttpException extends \RuntimeException
{
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        if ($previous !== null) {
            $message = $this->makeMessage($previous, $message);
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @param \Exception $exception
     * @param string     $message
     *
     * @return string
     */
    protected function makeMessage(\Exception $exception, $message = ''): string
    {
        $messages = [];
        if (!empty($message)) {
            $messages[] = $message;
        }
        $messages[] = $exception->getMessage();

        if (\method_exists($exception, 'getRequest') && $exception->getRequest() instanceof RequestInterface) {
            return $this->messageString($exception->getRequest(), $exception->getMessage());
        }

        return \implode("\n", $messages);
    }

    private function messageString(RequestInterface $request, $message = ''): string
    {
        if (empty($message)) {
            $message = 'Fail';
        }

        return \sprintf('%s: %s', (string) $request->getUri(), $message);
    }
}
