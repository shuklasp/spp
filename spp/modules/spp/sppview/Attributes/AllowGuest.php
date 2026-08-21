<?php
declare(strict_types=1);

namespace SPPMod\SPPView\Attributes;

/**
 * #[AllowGuest]
 * Marks a LiveComponent as publicly accessible without authentication.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AllowGuest
{
}
