<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var BookingRepository
     * @var Distance
     * @var Job
     */
    protected $repository;
    protected $distance;
    protected $job;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     * @param Distance $distance
     * @param Job $job
     */
    public function __construct(
        BookingRepository $bookingRepository,
        Distance $distance,
        Job $job
    ) {
        $this->repository = $bookingRepository;
        $this->distance = $distance;
        $this->job = $job;
    }

    /**
     * @return mixed
     */
    public function index(Request $request)
    {
        $userId = $request->get('user_id');
        $userType = $request->__authenticatedUser->user_type;
        if (!empty($user_id)) {
            $response = $this->repository->getUsersJobs($userId);
        } elseif ($this->isUserTypeAdmin($userType)) {
            $response = $this->repository->getAll($request);
        }

        return response($response);
    }

    private function isUserTypeAdmin(string $userType): bool
    {
        return $userType === env('ADMIN_ROLE_ID') || $userType === env('SUPERADMIN_ROLE_ID') ? true : false;
    }

    /**
     * @return mixed
     */
    public function show(int $id)
    {
        $job = $this->repository->getUsersJobs($id);

        return response($job);
    }

    /**
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->store($authenticatedUser, $data);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function update(int $id, Request $request)
    {
        $data = $request->all();
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->updateJob(
            $id,
            array_except($data, ['_token', 'submit']),
            $authenticatedUser,
        );

        return response($response);
    }

    /**
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $user_id = $request->get('user_id');
        if ($user_id) {
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
        }

        return response($response);
    }

    /**
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->acceptJob($data, $authenticatedUser);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function acceptJobWithId(Request $request)
    {
        $jobId = $request->get('job_id');
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->acceptJobWithId($jobId, $authenticatedUser);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->cancelJobAjax($data, $authenticatedUser);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->endJob($data);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function customerNotCall(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->customerNotCall($data);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->getPotentialJobs($authenticatedUser);

        return response($response);
    }

    /**
     * @return mixed
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->all();
        $distance = "";
        $time = "";
        $jobId = "";
        $session = "";
        $adminComment = "";
        $flagged = $data['flagged'];
        $byAdmin = $data['by_admin'];
        $manuallyHandled = $data['manually_handled'];
        $isDistanceUpdated = false;
        $isJobUpdated = false;

        if (isset($data['distance']) && $data['distance'] !== '') {
            $distance = $data['distance'];
        }

        if (isset($data['time']) && $data['time'] !== '') {
            $time = $data['time'];
        }

        if (isset($data['jobid']) && $data['jobid'] !== '') {
            $jobId = $data['jobid'];
        }

        if (isset($data['session_time']) && $data['session_time'] !== '') {
            $session = $data['session_time'];
        }

        if ($flagged && $data['admincomment'] === '') {
            $data['admincomment'] = "Please, add comment";
        }

        if (isset($data['admincomment']) && $data['admincomment'] !== "") {
            $adminComment = $data['admincomment'];
        }

        if ($time !== '' || $distance !== '' ) {
            $isDistanceUpdated = $this->distance
                ->where('job_id', $jobId)
                ->update(
                    array(
                        'distance' => $distance,
                        'time' => $time
                    )
                );
        }

        if ($adminComment !== '' || $session !== '' || $flagged || $manuallyHandled || $byAdmin) {
            $isJobUpdated = $this->job
                ->find($jobId)
                ->update(
                    array(
                        'admin_comments' => $adminComment,
                        'flagged' => $flagged,
                        'session_time' => $session,
                        'manually_handled' => $manuallyHandled,
                        'by_admin' => $byAdmin
                    )
                );
        }

        if ($isDistanceUpdated || $isJobUpdated) return response('Record updated!');
    }

    /**
     * @return mixed
     */
    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);

        try {
            $this->repository->sendNotificationTranslator($job, $jobData, '*');
            return response(['success' => 'Push sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }
}
