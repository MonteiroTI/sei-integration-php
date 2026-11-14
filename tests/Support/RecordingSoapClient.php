<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Tests\Support;

use SoapClient;

/**
 * SoapClient real (serialização da ext-soap) que não acessa a rede:
 * guarda o XML enviado e devolve uma resposta programada.
 */
final class RecordingSoapClient extends SoapClient
{
    public ?string $requestXml = null;

    public string $responseXml = '';

    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false, ?string $uriParserClass = null): ?string
    {
        $this->requestXml = $request;

        return $this->responseXml;
    }

    public static function envelope(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
            .' xmlns:ns1="Sei" xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"'
            .' SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'
            .'<SOAP-ENV:Body>'.$body.'</SOAP-ENV:Body></SOAP-ENV:Envelope>';
    }
}
