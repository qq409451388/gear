<?php
class WebSocketInterpreter implements Interpreter
{

    public function getSchema(): string
    {
        return "ws";
    }

    /**
     * Http协议申请升级WebSocket 握手动作
     * @param $buffer
     * @return string
     */
    public function doHandShake($buffer) {
        $key = $this->getHeaders($buffer);
        //必须以两个回车结尾
        return "HTTP/1.1 101 Switching Protocol\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: " . $this->calcKey($key) . "\r\n\r\n";
    }

    //获取请求头
    private function getHeaders( $req ) {
        $key = null;
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) {
            $key = $match[1];
        }
        return $key;
    }

    //验证socket
    private function calcKey( $key ) {
        //基于websocket version 13
        return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }

    /**
     * @param WebSocketResponse $response
     * @return string
     */
    public function encode(IResponse $response): string
    {
        return $response->isHandShake ? $response->response : $this->frame($response->response);
    }

    private function frame( $buffer ) {
        $len = strlen($buffer);
        if ($len <= 125) {
            return "\x81" . chr($len) . $buffer;
        } else if ($len <= 65535) {
            return "\x81" . chr(126) . pack("n", $len) . $buffer;
        } else {
            return "\x81" . chr(127) . pack("xxxxN", $len) . $buffer;
        }
    }

    public function decode(string $content): IRequest
    {
        $content = $this->decodeBuffer($content);
        $request = new WebSocketRequest();
        $request->sourceData = EzCollectionUtils::decodeJson($content);
        DBC::assertNotEmpty($request->sourceData['method'],
            "[WebSocketInterpreter] Request Data must Has Key method!", 0, GearIllegalArgumentException::class);
        DBC::assertNotEmpty($request->sourceData['data'],
            "[WebSocketInterpreter] Request Data must Has Key data!", 0, GearIllegalArgumentException::class);
        $request->setPath($request->sourceData['method']);
        if (EzWebSocketMethodEnum::METHOD_CONTRACT == $request->getPath()) {
            $data = EzWebSocketRequestContract::create($request->sourceData['data']);
        } else if (EzWebSocketMethodEnum::METHOD_CALL == $request->getPath()) {
            $data = EzWebSocketRequestCall::create($request->sourceData['data']);
        } else if (EzWebSocketMethodEnum::METHOD_SERVER == $request->getPath()) {
            $data = EzWebSocketRequestServer::create($request->sourceData['data']);
        } else {
            $data = null;
        }
        $request->setData($data);
        return $request;
    }

    private function decodeBuffer($buffer) {
        $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    public function getNotFoundResourceResponse(IRequest $request): IResponse
    {
        // TODO: Implement getNotFoundResourceResponse() method.
    }

    public function getNetErrorResponse(IRequest $request, string $errorMessage = ""): IResponse
    {
        // TODO: Implement getNetErrorResponse() method.
    }

    public function getDynamicResponse(IRequest $request): IResponse
    {

    }
}
