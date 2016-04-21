<?php
# Copyright (c) 2015, phpfmt and its authors
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

final class RemoveSemicolonAfterCurly extends AdditionalPass {
	const LAMBDA_CURLY_OPEN = 'LAMBDA_CURLY_OPEN';

	public function candidate(string $source, array $foundTokens): bool {
		if (isset($foundTokens[ST_CURLY_CLOSE], $foundTokens[ST_SEMI_COLON])) {
			return true;
		}

		return false;
	}

	public function format(string $source): string{
		$this->tkns = token_get_all($source);
		$this->code = '';
		$curlyStack = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_NAMESPACE:
			case T_CLASS:
			case T_TRAIT:
			case T_INTERFACE:

			case T_WHILE:
			case T_IF:
			case T_SWITCH:
			case T_FOR:
			case T_FOREACH:
				$touchedFunction = true;
				$this->appendCode($text);
				break;

			case T_FUNCTION:
				$touchedFunction = true;
				if (!$this->rightUsefulTokenIs(T_STRING)) {
					$touchedFunction = false;
				}
				$this->appendCode($text);
				break;

			case ST_CURLY_OPEN:
				$curlyType = ST_CURLY_OPEN;
				if (!$touchedFunction) {
					$curlyType = self::LAMBDA_CURLY_OPEN;
				}
				$curlyStack[] = $curlyType;
				$this->appendCode($text);
				break;

			case ST_CURLY_CLOSE:
				$curlyType = array_pop($curlyStack);
				$this->appendCode($text);

				if (self::LAMBDA_CURLY_OPEN != $curlyType && $this->rightUsefulTokenIs(ST_SEMI_COLON)) {
					$this->walkUntil(ST_SEMI_COLON);
				}
				break;

			default:
				$this->appendCode($text);
				break;
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription(): string {
		return 'Remove semicolon after closing curly brace.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample(): string {
		return <<<'EOT'
<?php
// From:
function xxx() {
    // code
};

// To:
function xxx() {
    // code
}
?>
EOT;
	}
}
