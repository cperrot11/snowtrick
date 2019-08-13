<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Picture;
use App\Entity\Trick;
use App\Form\CommentType;
use App\Form\TrickType;
use App\Repository\TrickRepository;
use App\Services\FileUploader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/trick")
 */
class TrickController extends AbstractController
{
       /**
     * @Route("/blog", name="trick_index", methods={"GET"})
     */
    public function index(TrickRepository $trickRepository): Response
    {
        $end = getenv('LIMIT');
        $tricks = $trickRepository->findAllTrick(0, $end);
        $more = $end<$tricks->count();
        return $this->render('trick/index.html.twig', [
            'title'=>'Blog snow trick',
            'tricks' => $tricks,
            'more' => $more,
        ]);
    }
    /**
     * @Route("/ajax/{click}", name="loadMore", methods={"GET"})
     */
    public function ajax(Request $request, TrickRepository $repository, $click=1)
    {
        $begin = (($click-1)*getenv('LIMIT'))+1;
        $end = getenv('LIMIT');
        $trick = $repository->findAllTrick($begin, $end);
        return $this->render('trick/displayMoreTrick.html.twig', [
            'tricks' => $trick,
        ]);
    }
    /**
     * @Route("/new", name="trick_new", methods={"GET","POST"})
     */
    public function new(Request $request, FileUploader $fileUploader, ObjectManager $manager): Response
    {
        $trick = new Trick();
        $trick->setCreatedAt(new \DateTime());
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);
        $manager->persist($trick);

        if ($form->isSubmitted() && $form->isValid()) {
            {
                foreach($trick->getPictures() as $pict){
                    /** @var UploadedFile $brochureFile */
                    $pict = $fileUploader->saveImage($pict);
                    $manager->persist($pict);
                }
            }
            $manager->flush();

            return $this->redirectToRoute('trick_index');
        }

        return $this->render('trick/new.html.twig', [
            'trick' => $trick,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="trick_show", methods={"GET","POST"})
     */
    public function show(Trick $trick, Request $request, ObjectManager $manager): Response
    {
        $comment = new Comment();
        $comment->setCreationDate(new \DateTime());

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $comment->setTrick($trick);
            $comment->setUser($this->getUser());

            $manager->persist($comment);
            $manager->flush();
        }
        return $this->render('trick/show.html.twig', [
            'trick' => $trick,
            'commentForm'=>$form->createView()
        ]);
    }

    /**
     * @Route("/{id}/edit", name="trick_edit")
     * @IsGranted("ROLE_USER")
     */
    public function edit(Trick $trick, Request $request, FileUploader $fileUploader, ObjectManager $manager): Response
    {
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Ajout image
            foreach($trick->getPictures() as $pict){
                /** @var UploadedFile $brochureFile */
                if (!$pict->getId()){
                    $pict = $fileUploader->saveImage($pict);
                    $manager->persist($pict);
                }
            }
            $manager->persist($trick);
            $manager->flush();

            return $this->redirectToRoute('trick_show', [
                'id' => $trick->getId(),
            ]);
        }

        return $this->render('trick/edit.html.twig', [
            'trick' => $trick,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/delete", name="trick_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Trick $trick): Response
    {
        if ($this->isCsrfTokenValid('delete'.$trick->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($trick);
            $entityManager->flush();
        }

        return $this->redirectToRoute('trick_index');
    }
}