<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

use Laminas\Form\Element;
use Laminas\Form\Form;

final class ContentForm extends Form
{
    // public function __construct
    public static function create(Content $content): Form
    {
        $form = new self;
        foreach ($content->getProperties() as $property) {
            if ($property->isAutoIncrement()) {
                continue;
            }

            $form->add($form->getPropertyInput($property));
        }

        return $form;
    }

    private function getPropertyInput(Property $property): Element
    {
        $element = new Element($property->getName());
        $element->setLabel($property->getLabel());

        // $value = $property->getValue();
        $element->setValue($property->getValue());
        return $element;
    }
}
