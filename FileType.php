<?php
/*
**	Rose\Ext\Shield\FileType
**
**	Copyright (c) 2019-2020, RedStar Technologies, All rights reserved.
**	https://rsthn.com/
**
**	THIS LIBRARY IS PROVIDED BY REDSTAR TECHNOLOGIES "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
**	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A 
**	PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL REDSTAR TECHNOLOGIES BE LIABLE FOR ANY
**	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
**	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
**	OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
**	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
**	USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\IO\Path;
use Rose\Text;

class FileType extends Rule
{
	public function getName ()
	{
		return 'file-type';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors)
	{
		$value = $this->getValue($context);
		$this->identifier = $value;

		if (\Rose\typeOf($val) != 'Rose\\Map')
			return false;

		if ($val->error != 0)
			return false;

		$value = Text::split(',', $value)->map(function($i) { return Text::trim($i); });

		if ($value->indexOf(Text::toLowerCase(Text::substring(Path::extname($val->name), 1))) === null)
			return false;

		return true;
	}
};

Shield::registerRule('file-type', 'Rose\Ext\Shield\FileType');
