<?php
/**
 * This file contains only the ProgramController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
use AppBundle\Model\Program;
use AppBundle\Repository\ProgramRepository;
use AppBundle\Model\Organizer;

/**
 * The ProgramController handles listing, creating and editing programs.
 */
class ProgramController extends Controller
{
    /**
     * Display a list of the programs.
     * @Route("/programs", name="Programs")
     * @Route("/programs/", name="ProgramsSlash")
     */
    public function indexAction()
    {
        return $this->render('programs/index.html.twig');
    }

    /**
     * Show a form to create a new program.
     * @Route("/programs/new", name="NewProgram")
     * @Route("/programs/new/", name="NewProgramSlash")
     * @param Request $request The request object generated by Symfony.
     */
    public function newAction(Request $request)
    {
        $organizer = new Organizer(
            $this->get('session')->get('logged_in_user')->username
        );

        $program = new Program($organizer, $this->container);
        $form = $this->getFormForProgram($program);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $program = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($program);
            $em->flush();

            return $this->redirectToRoute('Programs');
        }

        return $this->render('programs/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Build a form for the given program.
     * @param  Program $program
     * @return Form
     */
    private function getFormForProgram(Program $program)
    {
        return $this->createFormBuilder($program)
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    // new Type(\DateTime::class),
                ]
            ])
            ->add('organizerNames', CollectionType::class, [
                'entry_type' => TextType::class,
                'constraints' => new NotBlank(),
                'allow_add' => true,
                'allow_delete' => true,
                // 'prototype' => true,
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
    }
}