<?php

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\Classroom;
use App\Form\AdminClassroomType;
use App\Form\TeacherClassroomType;
use App\Form\ValidateManuallyType;
use App\Form\ValidateType;
use App\Repository\AbsenceRepository;
use App\Repository\ClassroomRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

            $this->generateAbsences($classroom, $userRepository, $manager);

            return $this->redirectToRoute('home');
        }

        return $this->render('pages/add.classroom.html.twig', [
            'role' => $user->getRoles(),
            'form' => $form->createView(),
            'current_menu' => 'add_classroom'
        ]);
    }

    public function generateAbsences($classroom, $userRepository, $manager)
    {
        $classroom_group = $classroom->getClassroomGroup();
        $users_entities = $userRepository->findByClassroomGroupId($classroom_group->getId());

        foreach ($users_entities as $user)
        {
            $absence = new Absence();
            $absence->setUserId($user->getId());
            $absence->setClassroomId($classroom->getId());
            $manager->persist($absence);
        }

        $manager->flush();
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
            'classroom_id' => $classroom_id,
            'current_menu' => 'home',
            'role' => $this->getUser()->getRoles()
        ]);
    }

    public function validateManually($classroom_id, AbsenceRepository $absenceRepository, UserRepository $userRepository, Request $request, EntityManagerInterface $manager)
    {
        if ($this->getUser()->getRoles() != ['ROLE_ADMIN'] && $this->getUser()->getRoles() != ['ROLE_SUPER_ADMIN'])
        {
            return $this->redirectToRoute("home");
        }
        else
        {
            $form = $this->createForm(ValidateManuallyType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid())
            {
                $validated_user = $form->get('users')->getData();
                $absence = $absenceRepository->findOneEntityByClassroomIdAndUserId($classroom_id, $validated_user->getId());

                if ($absence)
                {
                    $manager->remove($absence[0]);
                    $manager->flush();
                }
                else
                {
                    return $this->redirectToRoute($request->attributes->get('_route'), $request->attributes->get('_route_params'));
                }
            }

            return $this->render('pages/validatemanually.html.twig', [
                'form' => $form->createView(),
                'role' => $this->getUser()->getRoles()
            ]);
        }
    }

    public function showAbsents($classroom_id, AbsenceRepository $absenceRepository, UserRepository $userRepository)
    {
        if ($this->getUser()->getRoles() != ['ROLE_ADMIN'] && $this->getUser()->getRoles() != ['ROLE_SUPER_ADMIN'])
        {
            return $this->redirectToRoute("home");
        }
        else
        {
            $students = $this->getAbsentStudents($classroom_id, $absenceRepository, $userRepository);

            return $this->render('pages/showabsents.html.twig', [
                'students' => $students,
                'role' => $this->getUser()->getRoles()
            ]);
        }
    }

    public function getAbsentStudents($classroom_id, $absenceRepository, $userRepository)
    {
        $absences = $absenceRepository->findAllByClassroomId($classroom_id);

        $absent_students = [];
        foreach ($absences as $absence)
        {
            array_push($absent_students, $userRepository->findOneById($absence->getUserId()));
        }

        return $absent_students;
    }
}









