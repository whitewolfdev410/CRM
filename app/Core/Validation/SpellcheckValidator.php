<?php

namespace App\Core\Validation;

use Illuminate\Contracts\Validation\Validator;
use PhpSpellcheck\Spellchecker\Aspell;

class SpellcheckValidator
{
    /**
     * @param $attribute
     * @param $value
     * @param $parameters
     * @param Validator $validator
     *
     * @return false
     */
    public function validate($attribute, $value, $parameters, $validator)
    {
        if((int)request()->get("ignore_spellcheck") === 1) {
            return true;
        }
        
        $aspell = Aspell::create();
        $misspellings = $aspell->check($value, ['en_US']);

        $errors = [];
        
        foreach ($misspellings as $misspelling) {
            $errors[] = [
                'word' => $misspelling->getWord(),
                'line' => $misspelling->getLineNumber(),
                'offset' => $misspelling->getOffset(),
                'suggestion' => $misspelling->getSuggestions()
            ];            
        }

        if($errors) {
            $validator->errors()->add($attribute, [
                'data' => ['spellcheck' => $errors],
                'message' => trans('validation.spellcheck')
            ]);
        }
        
        return !$errors;
    }
}
