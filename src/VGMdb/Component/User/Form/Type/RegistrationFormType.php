<?php

namespace VGMdb\Component\User\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * User registration form.
 *
 * @author Gigablah <gigablah@vgmdb.net>
 */
class RegistrationFormType extends AbstractType
{
    private $class;

    /**
     * @param string $class The User class name
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        switch ($options['flowStep']) {
            case 1:
                $builder
                    ->add('username', null, array('label' => 'Username'))
                    ->add('email', 'email', array('label' => 'Email'));
                break;
            case 2:
                $builder
                    ->add('plainPassword', 'repeated',
                        array(
                            'type' => 'password',
                            'options' => array(),
                            'first_options' => array('label' => 'Password'),
                            'second_options' => array('label' => 'Confirm Password'),
                            'invalid_message' => 'Passwords do not match.',
                        )
                    );
                break;
        }
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'flowStep' => 1,
            'data_class' => $this->class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'intention'  => 'registration',
        ));
    }

    public function getName()
    {
        return 'user_registration';
    }
}
