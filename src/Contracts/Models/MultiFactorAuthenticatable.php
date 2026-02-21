<?php

namespace Bead\Contracts\Models;

use Bead\Contracts\Authentication\Credentials as CredentialsContract;

/** Contract for authenticatables that support MFA. */
interface MultiFactorAuthenticatable extends Authenticatable
{
    /** Whether the authenticatable has MFA enabled. */
    public function hasMultiFactorEnabled(): bool;

    /** Verify some credentials against the authenticatable's MFA setup. */
    public function verifyMultiFactor(CredentialsContract $credentials): bool;

    /** The types of MFA tha the autneticatable supports. */
    public function multiFactorMethods(): array;
}
