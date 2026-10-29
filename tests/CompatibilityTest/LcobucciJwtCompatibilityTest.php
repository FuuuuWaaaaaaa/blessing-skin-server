<?php

namespace Tests;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class LcobucciJwtCompatibilityTest extends TestCase
{
    public function test_builder_keeps_ignored_mutator_calls_for_yggdrasil_plugins(): void
    {
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText(str_repeat('x', 64))
        );
        $builder = $configuration->builder();

        // yggdrasil-api 5.2.1 targets the mutable v4 Builder API.
        $builder->relatedTo('profile-owner');
        $builder->withClaim('yggt', 'token-id');

        $token = $builder
            ->issuedBy('Yggdrasil-Auth')
            ->issuedAt(new DateTimeImmutable())
            ->getToken($configuration->signer(), $configuration->signingKey());

        $this->assertSame('profile-owner', $token->claims()->get('sub'));
        $this->assertSame('token-id', $token->claims()->get('yggt'));
    }
}
