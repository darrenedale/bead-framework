<?php

namespace Equit;

interface JsonExportable {
	public function toJson(?array $options = null): string;
}
