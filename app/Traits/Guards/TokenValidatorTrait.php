<?php

namespace Turso\PHP\Installer\Traits\Guards;

use Illuminate\Support\Carbon;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;

trait TokenValidatorTrait
{
    public function isValidToken(string $token, $database): bool
    {
        $parser = new Parser(new JoseEncoder());

        try {
            $token = $parser->parse($token);
            if ($token->claims()->get('jti') !== $database) {
                return false;
            }
            
            if (Carbon::parse($token->claims()->get('exp'))->setTimezone('Asia/Jakarta')->isPast()) {
                return false;
            }

            return true;
        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
            throw new \Exception('Oh no, an error: ' . $e->getMessage(), 1);
        }
    }
}
