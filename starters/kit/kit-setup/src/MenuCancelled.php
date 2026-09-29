<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use RuntimeException;

/**
 * Ctrl+C no menu: a criação para pelo caminho de "nada foi instalado".
 *
 * @internal
 */
final class MenuCancelled extends RuntimeException {}
