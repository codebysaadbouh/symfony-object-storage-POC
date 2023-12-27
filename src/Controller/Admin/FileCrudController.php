<?php

namespace App\Controller\Admin;

use App\Entity\File;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use Vich\UploaderBundle\Form\Type\VichFileType;

class FileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return File::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('label', 'Nom du fichier')
                ->setRequired(true),
            TextField::new('file', 'Fichier')
                ->setFormType(VichFileType::class)
                ->onlyOnForms(),
            TextField::new('filePath', 'Fichier')
                ->setTemplatePath('admin/fields/document_link.html.twig')
                ->hideOnForm(),
        ];
    }
}
