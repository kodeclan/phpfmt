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

final class SpaceAroundControlStructures extends AdditionalPass {
	/**
	 * @param $source
	 * @param $foundTokens
	 */
	public function candidate(string $source, array $foundTokens): bool {
		if (
			isset($foundTokens[T_IF]) ||
			isset($foundTokens[T_DO]) ||
			isset($foundTokens[T_WHILE]) ||
			isset($foundTokens[T_FOR]) ||
			isset($foundTokens[T_FOREACH]) ||
			isset($foundTokens[T_SWITCH])
		) {
			return true;
		}
		return false;
	}

	/**
	 * @param  $source
	 * @return mixed
	 */
	public function format(string $source): string{
		$this->tkns = token_get_all($source);
		$this->code = '';
		$isComment = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;

			switch ($id) {
			case ST_QUOTE:
				$this->appendCode($text);
				$this->printUntilTheEndOfString();
				break;
			case T_CLOSE_TAG:
				$this->appendCode($text);
				$this->printUntil(T_OPEN_TAG);
				break;
			case T_START_HEREDOC:
				$this->appendCode($text);
				$this->printUntil(T_END_HEREDOC);
				break;
			case T_CONSTANT_ENCAPSED_STRING:
				$this->appendCode($text);
				break;
			case T_COMMENT:
				$isComment = false;
				if (
					!$this->leftUsefulTokenIs([T_OPEN_TAG]) &&
					$this->rightTokenIs([
						T_IF,
						T_DO,
						T_FOR,
						T_FOREACH,
						T_SWITCH,
						T_WHILE,
						T_COMMENT,
						T_DOC_COMMENT,
					])
				) {
					$this->appendCode($this->newLine);
					$isComment = true;
				}
				$this->appendCode($text);
				break;
			case T_IF:
			case T_DO:
			case T_FOR:
			case T_FOREACH:
			case T_SWITCH:
				if (!$isComment) {
					$this->appendCode($this->newLine);
				}

				$this->appendCode($text);
				break;
			case T_WHILE:
				if (!$isComment) {
					$this->appendCode($this->newLine);
				}
				$this->appendCode($text);
				$this->printUntil(ST_PARENTHESES_OPEN);
				$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				if ($this->rightUsefulTokenIs(ST_SEMI_COLON)) {
					$this->printUntil(ST_SEMI_COLON);
					$this->appendCode($this->newLine);
				}
				break;
			case ST_CURLY_CLOSE:
				$this->appendCode($text);
				if (!$this->rightTokenIs([T_ENCAPSED_AND_WHITESPACE, ST_QUOTE, ST_COMMA, ST_SEMI_COLON, ST_PARENTHESES_CLOSE])) {
					$this->appendCode($this->newLine);
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
		return 'Add space around control structures.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample(): string {
		return <<<'EOT'
<?php
// From
if ($a) {

}
if ($b) {

}

// To
if ($a) {

}

if ($b) {

}
?>
EOT;
	}
}
