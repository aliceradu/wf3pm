<?php
namespace App\Controller;

use Twig\Environment;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use App\Entity\Product;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\ProductRepository;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Entity\CommentFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ProductController extends Controller
{
    public function addProduct(
        Environment $twig, 
        FormFactoryInterface $factory, 
        Request $request,
        ObjectManager $manager,
        SessionInterface $session,
        UrlGeneratorInterface $urlGenerator
    ){
        
        $product = new Product();
        $builder = $factory->createBuilder(FormType::class, $product);
        $builder
            ->add('name', TextType::class,
                ['label'=>'PRODUCT.NAME'],
                ['attr'=> ['placeholder' => 'PRODUCT.PLACEHOLDER.NAME'], 
                'required' => false])
            ->add('description', TextareaType::class, 
                ['label'=>'PRODUCT.DESCRIPTION'],
                ['required' => false, 
                 'attr' => ['placeholder' => 'PRODUCT.PLACEHOLDER.DESCRIPTION']   
                ])
            ->add('version', TextType::class,
                ['label'=>'PRODUCT.VERSION'],
                ['attr'=> ['placeholder' => 'PRODUCT.PLACEHOLDER.VERSION']])
            ->add('submit', SubmitType::class,
                ['label'=>'PRODUCT.SUBMIT'],
                ['attr' => ['class'=>'btn btn-dark']]);
        
               
        $form = $builder->getForm();
        
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid()){
            $manager->persist($product);
            $manager->flush();
            
            $session->getFlashBag()->add('info', 'Your product was created');
            
            return new RedirectResponse($urlGenerator->generate('homepage'));
        }
        
        
        return new Response(
            $twig->render(
                'Product/addProduct.html.twig',
                [
                    'formular'=>$form->createView()
                ]
            )
        );
    }

    public function listProduct(
        ObjectManager $manager,
        UrlGeneratorInterface $urlGenerator,
        Environment $twig,
        ProductRepository $product
        )
    {
      /*$productRepository = $manager->getRepository(Product::class);
      $product = $productRepository->findAll();*/
      
      return new Response(
          $twig->render(
              'Product/listProduct.html.twig',
              [
                  'product'=>$product->findAll()
              ]
              )   
          );
    }
    
    public function productDetails(
        Environment $twig,
        Request $request,
        FormFactoryInterface $formFactory, 
        TokenStorageInterface $tokenStorage,
        UrlGeneratorInterface $urlGenerator,
        ObjectManager $manager
        ){
        
        $id = $request->query->get('id');
        $repository = $this->getDoctrine()
                            ->getRepository(Product::class);
        $product = $repository->find($id);
        
              
        $comment = new Comment();
        $form = $formFactory->create(
            CommentType::class,
            $comment,
            ['stateless'=>true]
            );
        
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
           $tmpCommentFile = [];
           
            foreach ($comment->getFiles() as $fileArray){
                
                foreach ($fileArray as $file){
                                    
                    $name = sprintf(
                            '%s.%s',
                            Uuid::uuid1(),
                            $file->getClientOriginalExtension()
                        );
                    
                    $commentFile = new CommentFile();
                    $commentFile->setComment($comment)
                        ->setMemeType($file->getMimeType())
                        ->setName($file->getClientOriginalName())
                        ->setFileUrl('/upload/'.$name);
                    
                    $tmpCommentFile[] = $commentFile;
                    
                    $file->move(
                        __DIR__.'/../../public/upload',
                        $name
                        );
                        $manager->persist($commentFile);
                    }
                        
                }
                
                $token = $tokenStorage->getToken();
                if(!$token){
                    throw new \Exception();
                }
                $user = $token->getUser();
                if(!$user){
                    throw new \Exception();
                }
            $comment->setFiles($tmpCommentFile)
                    ->setAuthor($user)
                    ->setProduct($product);
            
            $manager->persist($comment);
            $manager->flush();
            
            return new RedirectResponse($urlGenerator->generate('details_product')."?id=$id");
        }
                                
               
        return new Response(
          $twig->render(
            'Product/detailsProduct.html.twig',
              [
                  'product'=>$product,
                  'formComment'=>$form->createView()
              ]
              )
         );    
    }
}

