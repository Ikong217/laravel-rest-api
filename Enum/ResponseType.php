<?php
namespace App\Facades\Enum;

enum ResponseType: string {
    case JSON   = 'json';
    case String = 'string';
    case XML    = 'xml';
    case Plain  = 'plain'; // plain text, message only

    public function generate(array $data, int $code = 200)
    {
        return match ($this) {
            self::JSON   => response()->json($data, $code),
            self::String => response(json_encode($data), $code),
            self::XML    => response($this->toXml($data), $code)->header('Content-Type', 'application/xml'),
            self::Plain  => response($data['message'] ?? '', $code),
        };
    }

    private function toXml(array $data, string $root = 'response'): string
    {
        $xml = new \SimpleXMLElement("<{$root}/>");
        $this->arrayToXml($data, $xml);
        return $xml->asXML();
    }

    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            $key = is_int($key) ? "item{$key}" : $key;
            if (is_array($value)) {
                $this->arrayToXml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }
}
