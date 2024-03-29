<?php
/*
**	Rose\Ext\Shield\Rule
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

use Rose\Expr;
use Rose\Arry;
use Rose\Map;
use Rose\Text;

/*
**	Shield validation rule.
*/

abstract class Rule
{
	protected $baseIdentifier;
	protected $identifier;
	protected $value;

	public function __construct ($value, $baseIdentifier=null)
	{
		$this->value = $value;
		$this->baseIdentifier = $baseIdentifier;
		$this->identifier = null;
	}

	protected function valueIsString ()
	{
		return \Rose\typeOf($this->value) == 'Rose\\Arry' && $this->value->length == 1 && $this->value->get(0)->type == 'string';
	}

	protected function getValue ($context)
	{
		return Expr::value ($this->value, $context);
	}

	public abstract function getName();

	public function getIdentifier()
	{
		$val = $this->baseIdentifier ? $this->baseIdentifier : $this->identifier;
		return $val ? (\Rose\isString($val) && $val[0] == '@' ? Text::substring($val, 1) : $this->getName() . ':' . $val) : $this->getName();
	}

	public abstract function validate ($name, &$value, $input, $output, $context, $errors);
};
