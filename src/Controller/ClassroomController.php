<?php

namespace App\Controller;

use App\Entity\Classroom;
use App\Form\AdminClassroomType;
use App\Form\TeacherClassroomType;
use App\Form\ValidateType;
use App\Repository\ClassroomRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class ClassroomController extends AbstractController
{
    public function addClassroom(Request $request, EntityManagerInterface $manager, UserRepository $userRepository)
    {
        $user = $userRepository->findUserById($this->getUser());

        $classroom = new Classroom();

        if ($user->getRoles() == ['ROLE_ADMIN']) //si le user est un professeur
        {
            $classroom->setTeacher($user);
            $form = $this->createForm(TeacherClassroomType::class, $classroom);
        } else if ($user->getRoles() == ['ROLE_SUPER_ADMIN']) {
            $form = $this->createForm(AdminClassroomType::class, $classroom);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $manager->persist($classroom);
            $manager->flush();

            return $this->redirectToRoute('home');
        }

        return $this->render('pages/add.classroom.html.twig', [
            'role' => $user->getRoles(),
            'form' => $form->createView(),
            'current_menu' => 'add_classroom'
        ]);
    }

    public function editClassroom($classroom_id, ClassroomRepository $classroomRepository, Request $request)
    {
        $user = $this->getUser();
        $classroom = $classroomRepository->findByOneById($classroom_id);

        if ($user->getRoles() == ['ROLE_ADMIN']) //si le user est un professeur
        {
            if ($user->getId() != $classroom->getTeacher()->getId()) return $this->redirectToRoute('home'); //Sécurité
            $form = $this->createForm(TeacherClassroomType::class, $classroom);
        }
        else if ($user->getRoles() == ['ROLE_SUPER_ADMIN']) //si le user est un admin
        {
            $form = $this->createForm(AdminClassroomType::class, $classroom);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();
            return $this->redirectToRoute('home');
        }

        return $this->render('pages/edit.classroom.html.twig', [
            'form' => $form->createView(),
            'role' => $user->getRoles(),
            'current_menu' => 'edit_classroom'
        ]);
    }

    public function showQrCode($classroom_id, ClassroomRepository $classroomRepository)
    {
        $classroom = $classroomRepository->findByOneById($classroom_id);

        return $this->render('pages/showqrcode.classroom.html.twig', [
            'classroom' => $classroom,
            'current_menu' => 'home',
            'role' => $this->getUser()->getRoles()
        ]);
    }
}









