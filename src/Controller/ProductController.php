<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use App\Form\AddProductType;
use App\Form\AddCategoryType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ProductController extends AbstractController
{
    #[Route('/', name: 'app')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $products = $entityManager->getRepository(Product::class)->findAll();

        return $this->render('/product/index.html.twig', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    #[Route('/{category}', name: 'traitement_product')]
    public function showAll(EntityManagerInterface $entityManager, $category): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $categories_name = array_column($categories,"name");
        if (in_array($category, $categories_name)) {
            $category = $entityManager->getRepository(Category::class)->findId($category);
            $products = $entityManager->getRepository(Product::class)->findByCategory($category[0]->id);
            return $this->render('product/index.html.twig', [
                'categories' => $categories,
                'products' => $products,
            ]);
        } else {
            $this->addFlash('msg', "Catégorie $category non valide");
            return $this->redirectToRoute('app');
        }
    }

    #[Route('/product/add', name: 'add_product')]
    public function new(EntityManagerInterface $entityManager, Request $request): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $new_product = new Product();

        $form = $this->createForm(AddProductType::class, $new_product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $slug = implode("-", explode(" ", $name));
            $new_product->setSlug($slug);

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $newfilename = $slug . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter("images_directory"), $newfilename); // cf services.yaml
                    $new_product->setImage($newfilename);
                } catch (FileException $e) {
                    die("Erreur upload image " . $e);
                }
            }

            $entityManager->persist($new_product);
            $entityManager->flush();
            return $this->redirectToRoute('app');
        }

        return $this->render('product/add_product.html.twig', [
            'categories' => $categories,
            'form' => $form,
            'isUpdate' => false
        ]);
    }

    #[Route('/category/add', name: 'add_category')]
    public function newCat(EntityManagerInterface $entityManager, Request $request): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $new_category = new Category();

        $form = $this->createForm(AddCategoryType::class, $new_category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($new_category);
            $entityManager->flush();
            return $this->redirectToRoute('add_product');
        }

        return $this->render('product/add_category.html.twig', [
            'categories' => $categories,
            'form' => $form,
        ]);
    }

    #[Route('/product/del/{id}', name: 'del_product')]
    public function del(EntityManagerInterface $entityManager, $id): Response 
    {
        $toDel = $entityManager->getRepository(Product::class)->find($id);
        if ($toDel) {
            if ($toDel->image) {
                $file = $this->getParameter('images_directory') . $toDel->image;
                unlink($file);
            }

            $this->addFlash('success_del', "Produit $toDel->name supprimé");
            $entityManager->remove($toDel);
            $entityManager->flush();
        } else {
            $this->addFlash('fail_del', "Produit avec comme id $id non trouvé");
        }

        return $this->redirectToRoute('app');
    }

    #[Route('/product/{slug}', name: 'show_product')]
    public function view(EntityManagerInterface $entityManager, $slug): Response

    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $product = $entityManager->getRepository(Product::class)->findBySlug($slug);
        if ($product) {
            $product = $product[0];
        } else {
            $this->addFlash('fail_slug', "Produit $slug non valide");
            return $this->redirectToRoute('app');
        }
        return $this->render('product/show_product.html.twig', [
            'categories' => $categories,
            'product' => $product
        ]);
    }

    #[Route('/product/edit/{id}', name: 'edit_product')]
    public function edit(EntityManagerInterface $entityManager, Request $request, $id): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $new_product = $entityManager->getRepository(Product::class)->find($id);


        $form = $this->createForm(AddProductType::class, $new_product);
        $old_image = $new_product->image;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $name = $form->get('name')->getData();
            $slug = implode("-", explode(" ", $name));

            $new_product->setSlug($slug);

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $newfilename = $slug . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter("images_directory"), $newfilename); // cf services.yaml
                    $new_product->setImage($newfilename);
                } catch (FileException $e) {
                    die("Erreur upload image " . $e);
                }
            } else {
                $new_product->setImage($old_image);
            }

            $entityManager->flush();
            return $this->redirectToRoute('app');
        }

        return $this->render('product/add_product.html.twig', [
            'categories' => $categories,
            'form' => $form,
            'product' => $new_product,
            'isUpdate' => true
        ]);
    }
}
