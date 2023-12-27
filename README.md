# Intégration d'un service d'Object Storage compatible Amazon S3 Storage dans Symfony 

> ## **Object Storage**
> L'Object Storage est une architecture de stockage permettant de stocker des données non structurées.
> Cette architecture divise les données en unités (objets) et les stocke dans un environnement de données plat.
> Chaque objet comprend les données, les métadonnées et un identifiant unique que les applications peuvent utiliser pour faciliter la consultation et la récupération.
> Les systèmes de stockage d'objets permettent de conserver des quantités massives de données non structurées dans lesquelles les données sont écrites une fois et lues une fois (ou plusieurs fois).
> Le stockage d'objets est utilisé à des fins telles que le stockage d'objets tels que des vidéos et des photos sur Facebook, des chansons sur Spotify ou des fichiers dans des services de collaboration en ligne, tels que Dropbox.
> L'une des limites du stockage objet est qu'il n'est pas destiné aux données transactionnelles, car le stockage objet n'a pas été conçu pour remplacer l'accès et le partage de fichiers NAS ;
> il ne prend pas en charge les mécanismes de verrouillage et de partage nécessaires pour conserver une version unique et mise à jour avec précision d'un fichier.


### Construit avec :

<br/>
<img src="https://flysystem.thephpleague.com/img/flysystem.svg?animated=yes" width="100" alt="MinIO Logo">  
<br/>

> Flysystem est une bibliothèque de stockage de fichiers pour PHP. Elle fournit une interface pour interagir avec de nombreux types de systèmes de fichiers. Lorsque vous utilisez Flysystem, vous n'êtes pas seulement protégé contre le verrouillage des fournisseurs, vous aurez également une expérience cohérente pour tout type de stockage qui vous convient.


<img src="https://min.io/resources/img/logo.svg" width="150" alt="MinIO Logo">
<br/>

> **Min**IO est un serveur de stockage d’objets open-source populaire compatible avec le service de stockage cloud Amazon S3. Les applications qui ont été configurées pour parler à Amazon S3 peuvent également être configurées pour parler à Minio, ce qui permet à Minio d’être une alternative viable à S3 si vous voulez plus de contrôle sur votre serveur de stockage d’objets. Le service stocke des données non structurées telles que des photos, des vidéos, des fichiers journaux, des sauvegardes et des images de conteneurs/VM, et peut même fournir un seul serveur de stockage d’objets qui regroupe plusieurs lecteurs répartis sur plusieurs serveurs. 
> 
> ![MinIO Console · 1.10pm · 12-26.jpeg](public%2Fimages%2FMinIO%20Console%20%C2%B7%201.10pm%20%C2%B7%2012-26.jpeg)
> 
> Minio est écrit en Go, est fourni avec un client en ligne de commande et une interface de navigateur, et prend en charge un service de mise en file d’attente simple pour les cibles AMQP (Advanced Message Queuing Protocol), Elasticsearch, Redis, NATS et PostgreSQL. Pour toutes ces raisons, apprendre à mettre en place un serveur de stockage d’objets Minio peut ajouter une vaste palette de flexibilité et d’utilité à votre projet.

<br/>

## Création d'une application symfony

> L'idée est de créer rapidement une application avec l'aide de EasyAdmin Bundle qui nous permettra de deployer très rapidement une interface de gestion des fichiers à uploader

### Initialisation d'un nouveau projet 

```shell
symfony new symfonyocpoc --version="6.4.*" --webapp
```

### Configuration d'un fichier compose.yaml pour la base de données
```shell
symfony console make:docker:database
```
- **NB :** Nous utiliserons dans notre cas Postgres

#### `compose.yaml`

```yaml
version: '3'

services:
###> doctrine/doctrine-bundle ###
  symfonyospocDB:
    image: postgres:${POSTGRES_VERSION:-15}-alpine
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-symfonyospoc}
      # You should definitely change the password in production
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-password}
      POSTGRES_USER: ${POSTGRES_USER:-codebysaadbouh}
    volumes:
      - database_data:/var/lib/postgresql/data:rw
      # You may use a bind-mounted host directory instead, so that it is harder to accidentally remove the volume and lose all your data!
      # - ./docker/db/data:/var/lib/postgresql/data:rw
###< doctrine/doctrine-bundle ###

volumes:
  ###> doctrine/doctrine-bundle ###
  database_data:
###< doctrine/doctrine-bundle ###
```

#### `compose.override.yaml`

```yaml
version: '3'

services:
###> doctrine/doctrine-bundle ###
  symfonyospocDB:
    ports:
      - "5432:5432"
###< doctrine/doctrine-bundle ###

###> symfony/mailer ###
  symfonyospocMAIL:
    image: schickling/mailcatcher
    ports: ["1025", "1080"]
###< symfony/mailer ###
```
### Installer VichUploaderBundle

```php  
composer require vich/uploader-bundle  
```

### Création de l'entité File & migration en base de données

####  `File.class`

```PHP
<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File as HttpFile;
use Gedmo\Mapping\Annotation as Gedmo;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[Vich\Uploadable]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $filePath = null;

    // NOTE: This is not a mapped field of entity metadata, just a simple property.
    #[Vich\UploadableField(mapping: 'files', fileNameProperty: 'filePath', size: 'fileSize')]
    private ?HttpFile $file = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     * @param HttpFile|null $file
     */
    public function setFile(?HttpFile $file = null): void
    {
        $this->file = $file;

        if (null !== $file) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }
    }


    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getFile(): ?HttpFile
    {
        return $this->file;
    }

    public function __toString(): string
    {
        return $this->filePath;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

}
```

#### Migration
```shell
symfony console make:migration
```
```shell
symfony console doctrine:migration:migrate
```

### Installation de easy-admin

```shell
composer require easycorp/easyadmin-bundle
```

#### Création d'un dashboard 

```shell
symfony console make:admin:dashboard
``` 


#### `DashboardController.php`
```php
<?php

namespace App\Controller\Admin;

use App\Entity\File;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        //return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        
        // create the admin folder in the template directory
        // Create in this new folder the file index.html.twig and put {% extends '@EasyAdmin/page/content.html.twig' %}
        return $this->render('admin/index.html.twig');

    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('symfonyospoc');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('File', 'fas fa-file', File::class);
    }
}
```

#### Création d'un CRUD controller pour File

```shell
symfony console make:admin:crud 
```

#### `FileCrudController.php`
```php
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
```
## Installation de Flysystem et de son extension de prise en charge du service S3

```shell
composer require league/flysystem-bundle
```

```shell
composer require league/flysystem-aws-s3-v3:^3.0 
```

> Avant de configurer notre service nous allons modifier le compose.yaml pour ajouter notre service
> **MIN**IO
> ```yaml
>SymfonyospocMINio:
>   image: minio/minio
>   environment:
>     MINIO_ROOT_USER: codebysaadbouh
>     MINIO_ROOT_PASSWORD: password
>   volumes:
>     - ./data/minio:/data
>   command: server /data --console-address ":9001"
>   ports:
>     - "9000:9000"
>     - "9001:9001"
>```
> 
> Nous allons lancer notre docker compose et se connecter avec les credentials définis
> 
> ![MinIO Console · 1.10pm · 12-26.jpeg](public%2Fimages%2FMinIO%20Console%20%C2%B7%201.10pm%20%C2%B7%2012-26.jpeg)
> 
> **NB :** Nous allons créer un nouveau bucket et le rendre public
>  
> ![MinIO Console · 1.37pm · 12-26.jpeg](public%2Fimages%2FMinIO%20Console%20%C2%B7%201.37pm%20%C2%B7%2012-26.jpeg)

## Configuration du nouveau service Aws\S3\S3Client

`config/services.yaml`
```yaml
# This file is the entry point to configure your own services.
# Files in the packages/ subdirectory configure your dependencies.

# Put parameters here that don't need to change on each machine where the app is deployed
# https://symfony.com/doc/current/best_practices.html#use-parameters-for-application-configuration
parameters:

services:
    # default configuration for services in *this* file
    _defaults:
        autowire: true      # Automatically injects dependencies in your services.
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.

    # makes classes in src/ available to be used as services
    # this creates a service per class whose id is the fully-qualified class name
    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'

    # add more service definitions when explicit configuration is needed
    # please note that last definitions always *replace* previous ones
    Aws\S3\S3Client:
        arguments:
            - version: 'latest'
              region: 'eu-east-1'
              endpoint: 'http://127.0.0.1:9000'
              credentials:
                  key: 'codebysaadbouh'
                  secret: 'password'
```

## Modification du fichier de configuration de flysystem

`config/packages/flysystem.yaml`
```yaml
# Read the documentation at https://github.com/thephpleague/flysystem-bundle/blob/master/docs/1-getting-started.md
flysystem:
    storages:
        default.storage:
            adapter: 'local'
            options:
                directory: '%kernel.project_dir%/public/file'
        aws.storage:
            adapter: 'aws'
            options:
                client: Aws\S3\S3Client
                bucket: 'file'
```

## Modification du fichier de configuration de vich uploader

`config/packages/vich_uploader.yaml`
```yaml
vich_uploader:
    db_driver: orm
    storage: flysystem
    mappings:
        files:
            uri_prefix: /file
            namer: Vich\UploaderBundle\Naming\SmartUniqueNamer
            upload_destination: aws.storage
```

### Adapter de basePath de notre field template pour la consultation du fichier

Reference : `setTemplatePath('admin/fields/document_link.html.twig')` <br/>
Template  : `templates/admin/fields/document_link.html.twig`

```html

{% if field.value %}
    <a href="http://127.0.0.1:9000/file/{{ field.value }}" target="_blank">
        Consulter le fichier
    </a>
{% else %}
    --
{% endif %}
```

### Interface de gestion des fichiers
![File.jpeg](public%2Fimages%2FFile.jpeg)


### Explorateur d'objets sur MINIO

![MinIO Console · 4.04pm · 12-26.jpeg](public%2Fimages%2FMinIO%20Console%20%C2%B7%204.04pm%20%C2%B7%2012-26.jpeg)


### Metrics sur MINIO
![MinIO Console · 10.35am · 12-27.jpeg](public%2Fimages%2FMinIO%20Console%20%C2%B7%2010.35am%20%C2%B7%2012-27.jpeg)

### Auteur
**Cheikh Saad Bouh SOW** - Analyste développeur, Chef de projet - [github](https://github.com/codebysaadbouh)
