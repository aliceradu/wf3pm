<?php
namespace App\Controller;

use Twig\Environment;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class UserController{
    
    public function registerUser
    (  
        Environment $twig,
        FormFactoryInterface $factory,
        Request $request,
        ObjectManager $manager,
        SessionInterface $session,
        UrlGeneratorInterface $urlGenerator,
        \Swift_Mailer $mailer
    )
    {
            
        $user = new User();
        $builder = $factory->createBuilder(FormType::class, $user);
        $builder
            ->add('username', TextType::class,
                ['required' => true,'label' => 'Username', 'attr'=> ['placeholder' => 'Username here please']])
            ->add('firstname', TextType::class,
                ['required' => true, 'label' => 'First Name', 'attr' => ['placeholder' => 'First name here please']])
            ->add('lastname', TextType::class,
                ['required' => true, 'label' => 'Last Name', 'attr'=> ['placeholder' => 'Last name here please']])
            ->add('email', EmailType::class,
                ['required' => true, 'label' => 'E-mail address', 'attr' => ['placeholder' => 'Email here please']])
            ->add(
                'password', 
                RepeatedType::class,
                [
                    'type' => PasswordType::class,
                    'invalid_message' => 'The password fields must match.',
                    
                    'options' => array('attr' => array ('class' => 'password-field', 'placeholder' => 'Password here please')),
                    'required' => true,
                    'first_options'  => array('label' => 'Password'),
                    'second_options' => array('label' => 'Repeat Password', 'attr' => array('placeholder' => 'Repeat password here please')),
                    ]
                )
            ->add(
                'submit', 
                SubmitType::class,
                ['attr' => ['class'=>'btn btn-dark']]);
                        
                        
        $form = $builder->getForm();
        
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid()){
            $manager->persist($user);
            $manager->flush();
            
            $message = new \Swift_Message();
            $message
                ->setFrom('wf3pm@localhost.com')
                ->setTo($user->getEmail())
                ->setSubject('Validate your account')
                ->setBody(
                    $twig->render(
                        'Mail/accountCreation.html.twig',
                        ['user'=> $user]
                        )
                    );
            
            $mailer->send($message); 
               
            
            $session->getFlashBag()->add('info', 'Your account was created. Please check your e-mails.');
            
            return new RedirectResponse($urlGenerator->generate('homepage'));
        }
                
        return new Response(
            $twig->render(
                'User/registerUser.html.twig',
                [
                    'registrationFormular'=>$form->createView()
                ]
            )
        );
    }
    
    public function activateUser($token, ObjectManager $manager, SessionInterface $session, UrlGeneratorInterface $urlGenerator)
    {
        
        $userRepository = $manager->getRepository(User::class);
        
        $user = $userRepository->findOneByEmailToken($token);
        
        if(!$user){
            throw new NotFoundHttpException('User not found for given token');
        }
        
        $user->setActive(true);
        $user->setEmailToken(null);
        $username = $user->getUsername();
        
        $manager->flush();
        $session->getFlashBag()->add('info', "Welcome $username!");
        
        
        return new RedirectResponse($urlGenerator->generate('homepage'));
    }
}