<?php

declare(strict_types=1);

/*
 * This file is part of Contao Manager.
 *
 * (c) Contao Association
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerApi\Controller;

use Contao\ManagerApi\Task\TaskManager;
use Contao\ManagerApi\Task\TaskStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/task", methods={"GET", "PUT", "PATCH", "DELETE"})
 */
class TaskController
{
    /**
     * @var TaskManager
     */
    private $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke(Request $request): Response
    {
        switch ($request->getMethod()) {
            case 'GET':
                return $this->getTask();

            case 'PUT':
                return $this->putTask($request);

            case 'PATCH':
                return $this->patchTask($request);

            case 'DELETE':
                return $this->deleteTask();
        }

        return new Response('', Response::HTTP_METHOD_NOT_ALLOWED);
    }

    private function getTask(): Response
    {
        return $this->getResponse($this->taskManager->updateTask());
    }

    private function putTask(Request $request): Response
    {
        if ($this->taskManager->hasTask()) {
            throw new BadRequestHttpException('A task is already active');
        }

        $name = $request->request->get('name');
        $config = $request->request->get('config', []);

        if (empty($name) || !\is_array($config)) {
            throw new BadRequestHttpException('Invalid task data');
        }

        return $this->getResponse($this->taskManager->createTask($name, $config));
    }

    private function patchTask(Request $request): Response
    {
        if (!$this->taskManager->hasTask()) {
            throw new BadRequestHttpException('No active task found.');
        }

        if (TaskStatus::STATUS_ABORTING !== $request->request->get('status')) {
            throw new BadRequestHttpException('Unsupported task status');
        }

        return $this->getResponse($this->taskManager->abortTask());
    }

    private function deleteTask(): Response
    {
        if (!$this->taskManager->hasTask()) {
            throw new BadRequestHttpException('No active task found.');
        }

        try {
            return $this->getResponse($this->taskManager->deleteTask());
        } catch (\RuntimeException $e) {
            return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
        }
    }

    private function getResponse(TaskStatus $status = null, int $code = Response::HTTP_OK): Response
    {
        if (!$status instanceof TaskStatus) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (!$status->getDetail()) {
            switch ($status->getStatus()) {
                case TaskStatus::STATUS_COMPLETE:
                    $status->setDetail('The background task was completed successfully. Check the console protocol for the details.');
                    break;

                case TaskStatus::STATUS_ERROR:
                    $status->setDetail('The background task has stopped unexpectedly. Please check the console protocol.');
                    break;
            }
        }

        return new JsonResponse(
            [
                'title' => $status->getTitle(),
                'summary' => $status->getSummary(),
                'detail' => $status->getDetail(),
                'console' => $status->getConsole(),
                'cancellable' => $status->isCancellable(),
                'autoclose' => $status->canAutoClose(),
                'audit' => $status->hasAudit(),
                'status' => $status->getStatus(),
            ],
            $code
        );
    }
}
