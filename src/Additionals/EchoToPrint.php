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

final class EchoToPrint extends AdditionalPass {
	public function candidate(string $source, array $foundTokens): bool {
		if (isset($foundTokens[T_ECHO])) {
			return true;
		}

		return false;
	}

	public function format(string $source): string{
		$this->tkns = token_get_all($source);
		while (list($index, $token) = each($this->tkns)) {
			list($id) = $this->getToken($token);
			$this->ptr = $index;

			if (T_ECHO == $id) {
				$start = $index;
				$end = $this->walkUsefulRightUntil($this->tkns, $index, [ST_SEMI_COLON, T_CLOSE_TAG]);
				$convert = true;
				for ($i = $start; $i < $end; $i++) {
					$tkn = $this->tkns[$i];
					if (ST_PARENTHESES_OPEN === $tkn[0]) {
						$this->refWalkBlock($this->tkns, $this->ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					} elseif (ST_BRACKET_OPEN === $tkn[0]) {
						$this->refWalkBlock($this->tkns, $this->ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
					} elseif (ST_COMMA === $tkn[0]) {
						$convert = false;
						break;
					}
				}
				if ($convert) {
					$this->tkns[$start] = [T_PRINT, 'print'];
				}
			}
		}

		return $this->render();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription(): string {
		return 'Convert from T_ECHO to print.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample(): string {
		return <<<'EOT'
<?php
echo 1;

print 2;
?>
EOT;
	}

}