<?php

namespace ACSEO\FormFeatureGeneratorBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
/**
 * Defaut controller.
 *
 * @Route("/default")
 */
class DefaultController extends Controller
{
    /**
     * index.
     *
     * @Route("/", name="index_default")
     * @Template()
     */
    public function indexAction($name)
    {
        return $this->render('ACSEOFormFeatureGeneratorBundle:Default:index.html.twig', array('name' => $name));
    }
}
