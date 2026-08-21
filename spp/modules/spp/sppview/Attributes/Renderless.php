<?php
declare(strict_types=1);

namespace SPPMod\SPPView\Attributes;

/**
 * #[Renderless]
 * Marks a LiveComponent method as skipping the render() call.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Renderless
{
}
