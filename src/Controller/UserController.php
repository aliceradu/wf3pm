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
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use App\Repository\RoleRepository;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class UserController{
    
    public function registerUser
    (  
        Environment $twig,
        FormFactoryInterface $factory,
        Request $request,
        ObjectManager $manager,
        SessionInterface $session,
        UrlGeneratorInterface $urlGenerator,
        \Swift_Mailer $mailer,
        EncoderFactoryInterface $encoderFactory,
        RoleRepository $roleRepository
    )
    {
            
        $user = new User();
        $builder = $factory->createBuilder(FormType::class, $user);
        $builder
            ->add('username', TextType::class,
                ['required' => true,'label' => 'USER.USERNAME', 'attr'=> ['placeholder' => 'USER.PLACEHOLDER.USERNAME']])
            ->add('firstname', TextType::class,
                ['required' => true, 'label' => 'USER.FIRST.NAME', 'attr' => ['placeholder' => 'USER.PLACEHOLDER.FIRST.NAME']])
            ->add('lastname', TextType::class,
                ['required' => true, 'label' => 'USER.LAST.NAME', 'attr'=> ['placeholder' => 'USER.PLACEHOLDER.LAST.NAME']])
            ->add('email', EmailType::class,
                ['required' => true, 'label' => 'USER.EMAIL.ADDRESS', 'attr' => ['placeholder' => 'USER.PLACEHOLDER.EMAIL.ADDRESS']])
            ->add(
                'password', 
                RepeatedType::class,
                [
                    'type' => PasswordType::class,
                    'invalid_message' => 'The password fields must match.',
                    
                    'options' => array('attr' => array ('class' => 'password-field', 'placeholder' => 'USER.PLACEHOLDER.PASSWORD')),
                    'required' => true,
                    'first_options'  => array('label' => 'USER.PASSWORD'),
                    'second_options' => array('label' => 'USER.REPEAT.PASSWORD', 'attr' => array('placeholder' => 'USER.PLACEHOLDER.REPEAT.PASSWORD')),
                    ]
                )
            ->add(
                'submit', 
                SubmitType::class,
                ['attr' => ['class'=>'btn btn-dark']]);
                        
                        
        $form = $builder->getForm();
        
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid()){
            
            $salt = md5($user->getUsername());
            $user->setSalt($salt);
            
            $encoder = $encoderFactory->getEncoder(User::class);
            
            $password = $encoder->encodePassword(
                $user->getPassword(), 
                $salt
                );
            
            $user->setPassword($password);
            
            $user->addRole($roleRepository->findOneByLabel('ROLE_USER'));
            
            $manager->persist($user);
            $manager->flush();
            
            $message = new \Swift_Message();
            $message
                ->setFrom('alicegabriela.radu@gmail.com')
                ->setTo($user->getEmail())
                ->setSubject('Validate your account')
                ->setBody(
                    $twig->render(
                        'Mail/accountCreation.txt.twig',
                        ['user'=> $user]
                        )
                    )
                -> addPart($twig->render(
                        'Mail/accountCreation.html.twig',
                        ['user'=> $user]
                        )
                        , 'text/plain'
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
    
    public function usernameAvailable(
        Request $request,
        UserRepository $repository){
        
        $username = $request->request->get('username');
        
        $unavailable = false;
        if(!empty($username)){
            $unavailable = $repository->usernameExist($username);
        }
        return new JsonResponse(
            [
            'available'=> !$unavailable
            ]
        );
       
    }
    public function login(
        AuthenticationUtils $authUtils,
        Environment $twig
        ){
        $errors = $authUtils->getLastAuthenticationError();
        $lastUsername = $authUtils->getLastUsername();
        
        return new Response(
            $twig->render('Security/login.html.twig',
            [
                'last_username'=>$authUtils->getLastUsername(),
                'error'=> $authUtils->getLastAuthenticationError()
            ]
         )       
      );
    }
    
}