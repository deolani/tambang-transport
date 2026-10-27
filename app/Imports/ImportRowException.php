<?php

namespace App\Imports;

use RuntimeException;

/** Kesalahan pada satu baris file import (pesan ditampilkan ke user). */
class ImportRowException extends RuntimeException {}
