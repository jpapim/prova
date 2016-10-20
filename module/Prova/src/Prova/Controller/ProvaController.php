<?php

namespace Prova\Controller;

use DOMPDFModule\View\Model\PdfModel;
use Estrutura\Controller\AbstractCrudController;
use Zend\View\Model\JsonModel;
use Estrutura\Helpers\Cript;
use Zend\View\Model\ViewModel;

class ProvaController extends AbstractCrudController
{
    /**
     * @var \Prova\Service\Prova
     */
    protected $service;

    /**
     * @var \Prova\Form\Prova
     */
    protected $form;

    public function __construct()
    {
        parent::init();
    }

    public function indexAction()
    {
        //return parent::index($this->service, $this->form);
        return new ViewModel([
            'service' => $this->service,
            'form' => $this->form,
            'controller' => $this->params('controller'),
            'atributos' => array()
        ]);
    }

    public function indexPaginationAction()
    {
        //http://igorrocha.com.br/tutorial-zf2-parte-9-paginacao-busca-e-listagem/4/

        $filter = $this->getFilterPage();

        $camposFilter = [
            '0' => [
                'filter' => "prova.nm_prova LIKE ?",
            ],
            '1' => [
                'filter' => "prova.dt_aplicacao_prova LIKE ?",
            ],
            '2' => [
                'filter' => "prova.dt_geracao_prova LIKE ?",
            ],
            '3' => [
                'filter' => "prova.ds_prova LIKE ?",
            ],

            '5' => NULL,

        ];


        $paginator = $this->service->getProvasPaginator($filter, $camposFilter);

        $paginator->setItemCountPerPage($paginator->getTotalItemCount());

        $countPerPage = $this->getCountPerPage(
            current(\Estrutura\Helpers\Pagination::getCountPerPage($paginator->getTotalItemCount()))
        );

        $paginator->setItemCountPerPage($this->getCountPerPage(
            current(\Estrutura\Helpers\Pagination::getCountPerPage($paginator->getTotalItemCount()))
        ))->setCurrentPageNumber($this->getCurrentPage());

        $viewModel = new ViewModel([
            'service' => $this->service,
            'form' => $this->form,
            'paginator' => $paginator,
            'filter' => $filter,
            'countPerPage' => $countPerPage,
            'camposFilter' => $camposFilter,
            'controller' => $this->params('controller'),
            'atributos' => array()
        ]);

        return $viewModel->setTerminal(TRUE);
    }

    public function gravarAction()
    {
        try {
            $controller = $this->params('controller');
            $request = $this->getRequest();
            $service = $this->service;
            $form = $this->form;

            if (!$request->isPost()) {
                throw new \Exception('Dados Inválidos');
            }

            $post = \Estrutura\Helpers\Utilities::arrayMapArray('trim', $request->getPost()->toArray());

            $files = $request->getFiles();
            $upload = $this->uploadFile($files);

            $post = array_merge($post, $upload);


            if (isset($post['id']) && $post['id']) {
                $post['id'] = Cript::dec($post['id']);
            }

            #Pegando o responsável pela criação do Plano de Mudança
            #xd($this->getServiceLocator()->get('Auth\Table\MyAuth')->read());
            $post['id_usuario'] = $this->getServiceLocator()->get('Auth\Table\MyAuth')->read()->id_usuario;
            $post['dt_aplicacao_prova'] = \Estrutura\Helpers\Data::converterDataBrazil2BancoMySQL($post['dt_aplicacao_prova']);

            $form->setData($post);

            if (!$form->isValid()) {
                $this->addValidateMessages($form);
                $this->setPost($post);
                $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'cadastro'));
                return false;
            }

            $service->exchangeArray($form->getData());
            $id_prova = $service->salvar();
            $this->addSuccessMessage('Registro gravado com sucesso');
            if (isset($post['id']) && $post['id']) {
                $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'cadastro-questao', 'id' => Cript::enc($post['id'])));
            } else {
                $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'cadastro-questao', 'id' => Cript::enc($id_prova)));
            }

            return $id_prova;
        } catch (\Exception $e) {

            $this->setPost($post);
            $this->addErrorMessage($e->getMessage());
            $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'cadastro'));
            return false;
        }

    }

    public function cadastroAction()
    {
        $id = Cript::dec($this->params('id'));
        $post = $this->getPost();
        $service = $this->service;
        $form = $this->form;

        if ($id) {
            $resultado = $service->buscar($id)->toArray();
            $resultado['dt_aplicacao_prova'] = \Estrutura\Helpers\Data::converterDataHoraBancoMySQL2Brazil($resultado['dt_aplicacao_prova']);
            $form->setData($resultado);
        }

        if (!empty($post)) {
            $form->setData($post);
        }

        $dadosView = [
            'service' => $service,
            'form' => $form,
            'controller' => $this->params('controller'),
            'atributos' => array()
        ];

        return new ViewModel($dadosView);
    }

    public function cadastroQuestaoAction()
    {
        $controller = $this->params('controller');
        $request = $this->getRequest();
        $service = $this->service;
        $form = $this->form;

        $id_prova = Cript::dec($this->params('id'));

        $obProvaService = new \Prova\Service\ProvaService();
        $dadosProva = $obProvaService->buscar($id_prova);

        $obQuestoesProvaService = new \QuestoesProva\Service\QuestoesProvaService();
        $arQuestoesProva = $obQuestoesProvaService->fetchAllByArrayAtributo(['id_prova'=>$id_prova]);

        $dadosView = [
            'service' => $service,
            'form' => $form,
            'controller' => $this->params('controller'),
            'dadosProva' => $dadosProva,
            'arQuestoesProva' => $arQuestoesProva,
            'atributos' => array(),
        ];

        return new ViewModel($dadosView);
    }

    public function excluirAction()
    {
        return parent::excluir($this->service, $this->form);
    }

    public function gerarRelatorioPdfAction()
    {
        $catequizandoService = new \Prova\Service\ProvaService();
        $arteste = $catequizandoService->fetchAll()->toArray();
        $pdf = new PdfModel();
        $pdf->setVariables(array(
            'caminho_imagem' => __DIR__,
            'inicio_contador' => 3,
            'teste' => $arteste,
        ));
        $pdf->setOption('filename', 'teste_pdf_'); // Triggers PDF download, automatically appends ".pdf"
        $pdf->setOption("paperSize", "a4"); //Defaults to 8x11
        $pdf->setOption("basePath", __DIR__); //Defaults to 8x11
        #$pdf->setOption("paperOrientation", "landscape"); //Defaults to portrait
        return $pdf;
    }

    public function adicionarQuestaoAleatoriaAction()
    {
        $controller = $this->params('controller');
        $request = $this->getRequest();
        $service = $this->service;
        $form = new \Prova\Form\QuestaoAleatoriaForm();

        $id_prova = Cript::dec($this->params('id'));

        $obProva = new \Prova\Service\ProvaService();
        $dadosProva = $obProva->buscar($id_prova);
        $dadosView = [
            'service' => $service,
            'form' => $form,
            'controller' => $this->params('controller'),
            'dadosProva' => $dadosProva,
            'atributos' => array(),
        ];

        return new ViewModel($dadosView);
    }

    public function gravarQuestaoAleatoriaAction()
    {
        try {

            $controller = $this->params('controller');
            $request = $this->getRequest();

            if (!$request->isPost()) {
                throw new \Exception('Dados Inválidos');
            }

            $post = \Estrutura\Helpers\Utilities::arrayMapArray('trim', $request->getPost()->toArray());

            #Alysson - O array $post['id'] armazena o ID da Prova
            if (isset($post['id']) && $post['id']) {
                $post['id'] = Cript::dec($post['id']);
                $id_prova = $post['id'];
            }

            #Prepara o array de filtros para trazer as questoes que atenam aos filtros selecionados
            $arrFiltro['bo_utilizavel'] = 'S';
            if (isset($post['id_tipo_questao']) && $post['id_tipo_questao']) {
                $arrFiltro['id_tipo_questao'] = $post['id_tipo_questao'];
            }
            if (isset($post['id_fonte_questao']) && $post['id_fonte_questao']) {
                $arrFiltro['id_fonte_questao'] = $post['id_fonte_questao'];
            }
            if (isset($post['id_assunto_materia']) && $post['id_assunto_materia']) {
                $arrFiltro['id_assunto_materia'] = $post['id_assunto_materia'];
            }
            if (isset($post['id_nivel_dificuldade']) && $post['id_nivel_dificuldade']) {
                $arrFiltro['id_nivel_dificuldade'] = $post['id_nivel_dificuldade'];
            }
            if (isset($post['id_classificacao_semestre']) && $post['id_classificacao_semestre']) {
                $arrFiltro['id_classificacao_semestre'] = $post['id_classificacao_semestre'];
            }

            #Busca as questoes que atendam aos filtros selecionados
            $questaoService = new \Questao\Service\QuestaoService();
            $resultado = $questaoService->fetchAllByArrayAtributo($arrFiltro);

            #Essa função mistura de forma aleatória os elementos de um array.
            shuffle($resultado);

            $arIdQuestoesSelecionadas = array();
            foreach($resultado as $item){
                $arIdQuestoesSelecionadas[] = $item['id_questao'];
            }

            #Chama o modulo que efetuara a gravacao na tabela Questoes_prova
            $questoes_provaService = new \QuestoesProva\Service\QuestoesProvaService();
            $resultQuestoesProva = $questoes_provaService->retornaQuestoesExistentes($arIdQuestoesSelecionadas, $id_prova );
            $arIdQuestoesExistentes = array();
            foreach($resultQuestoesProva as $objeto){
                $arIdQuestoesExistentes[] = $objeto['id_questao'];
            }

            #Este Código não permita inserir questões repetidas ao exame.
            foreach($resultado as $key => $item) {
                #Se a questao ja existir cadastrada para a prova, ela nao sera adicionada a prova.
                if(!in_array($item['id_questao'], $arIdQuestoesExistentes)) {
                    $dados['id_prova'] = $id_prova;
                    $dados['id_questao'] = $item['id_questao'];

                    #Grava na Tabela Questoes_Prova as questoes retornadas no filtro
                    $resultGravacao = $questoes_provaService->getTable()->salvar($dados, null);
                    if (!$resultGravacao) {
                        $this->setPost($post);
                        $this->addSuccessMessage('Houve problema ao relacionar a questao!');
                        $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'adicionar-questao-aleatoria', 'id' => Cript::enc($id_prova)));
                        return false;
                    }
                }
            }

            $this->addSuccessMessage('Questoes adicionadas na avaliaçao com sucesso! ');
            $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'cadastro-questao', 'id' => Cript::enc($id_prova)));
            return true;
        } catch (\Exception $e) {

            $this->setPost($post);
            $this->addErrorMessage($e->getMessage());
            $this->redirect()->toRoute('navegacao', array('controller' => $controller, 'action' => 'cadastro'));
            return false;
        }

    }

    public function imprimirProvaPdfAction() {
        $id_prova = Cript::dec($this->params()->fromRoute('id'));  // From RouteMatch
        $obProvaService = new \Prova\Service\ProvaService();
        $dadosProva = $obProvaService->buscar($id_prova);

        $obQuestoesProvaService = new \QuestoesProva\Service\QuestoesProvaService();
        $arQuestoesProva = $obQuestoesProvaService->fetchAllByArrayAtributo(['id_prova'=>$id_prova]);

        $pdf = new PdfModel();

        $pdf->setOption('filename', 'prova.pdf');
        $pdf->setOption('paperSize', 'a4');
        $pdf->setOption('paperOrientation', 'portrait');
        $pdf->setOption("basePath", __DIR__);

        $pdf->setVariables(array(
            'dadosProva' => $dadosProva,
            'arQuestoesProva' => $arQuestoesProva,
        ));

        return $pdf;
    }

    public function imprimirGabaritoPdfAction() {
        $id_prova = Cript::dec($this->params()->fromRoute('id'));  // From RouteMatch
        $obProvaService = new \Prova\Service\ProvaService();
        $dadosProva = $obProvaService->buscar($id_prova);

        $obQuestoesProvaService = new \QuestoesProva\Service\QuestoesProvaService();
        $arQuestoesProva = $obQuestoesProvaService->fetchAllByArrayAtributo(['id_prova'=>$id_prova]);

        $pdf = new PdfModel();

        $pdf->setOption('filename', 'gabarito_prova.pdf');
        $pdf->setOption('paperSize', 'a4');
        $pdf->setOption('paperOrientation', 'portrait');
        $pdf->setOption("basePath", __DIR__);

        $pdf->setVariables(array(
            'dadosProva' => $dadosProva,
            'arQuestoesProva' => $arQuestoesProva,
        ));

        return $pdf;
    }

}