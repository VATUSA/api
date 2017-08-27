<?php


namespace App\Http\Controllers\API\v1;


class CBTController
{
    // <editor-fold desc="CBT">
    public function getCBTBlocks($apikey)
    {
        $facility = Facility::where('apikey', $apikey)->first();

        $blocks = TrainingBlock::where('facility', $facility->id)->orderBy('order')->get();
        $data = [];
        $data['status'] = "success";
        foreach ($blocks as $block) {
            $data['blocks'][] =
                [
                    'id' => $block->id,
                    'order' => $block->order,
                    'name' => $block->name,
                    'visible' => $block->visible
                ];
        }
        echo json_encode($data);
    }

    public function getCBTChapters($apikey, $blockid)
    {
        $facility = Facility::where('apikey', $apikey)->first();

        $block = TrainingBlock::find($blockid);
        if ($block == null || empty($block)) {
            $data['status'] = "error";
            $data['msg'] = "Training block not found.";
            echo json_encode($data);
            exit;
        }
        if ($block->facility != $facility->id) {
            $data['status'] = "error";
            $data['msg'] = "Access denied.";
            echo json_encode($data);
            exit;
        }

        $data['status'] = "success";
        $data['blockId'] = $block->id;
        $data['blockName'] = $block->name;
        $chapters = $block->chapters()->get();
        foreach ($chapters as $chapter) {
            $data['chapters'][] =
                [
                    'id' => $chapter->id,
                    'order' => $chapter->order,
                    'name' => $chapter->name,
                    'url' => ((substr($chapter->url, 0, 4) == "http") ?
                        $chapter->url :
                        ((strlen($chapter->url) > 0) ? "https://docs.google.com/presentation/d/" . $chapter->url . "/embed?start=false&loop=false&delayms=60000" : "")),
                ];
        }

        echo json_encode($data);
    }

    public function getCBTChapter($apikey, $chapterid)
    {
        $facility = Facility::where('apikey', $apikey)->first();

        $chapter = TrainingChapter::find($chapterid);
        if ($chapter == null || empty($chapter)) {
            $data['status'] = "error";
            $data['msg'] = "Chapter not found.";
            echo json_encode($data);
            exit;
        }
        $block = $chapter->block()->first();
        if ($block->facility != $facility->id) {
            $data['status'] = "error";
            $data['msg'] = "Access denied.";
            echo json_encode($data);
            exit;
        }

        $data['status'] = "success";
        $data['chapter'] =
            [
                'id' => $chapter->id,
                'blockId' => $chapter->blockid,
                'order' => $chapter->order,
                'name' => $chapter->name,
                'url' => ((substr($chapter->url, 0, 4) == "http") ?
                    $chapter->url :
                    ((strlen($chapter->url) > 0) ? "https://docs.google.com/presentation/d/" . $chapter->url . "/embed?start=false&loop=false&delayms=60000" : "")),
            ];

        echo json_encode($data);
    }

    public function putCBTProgress(Request $request, $apikey, $cid)
    {
        parse_str(file_get_contents("php://input"), $vars);

        $facility = Facility::where('apikey', $apikey)->first();
        $chapterid = $vars['chapterId'];
        $chapter = TrainingChapter::find($chapterid);
        if ($chapter == null || empty($chapter)) {
            $data['status'] = "error";
            $data['msg'] = "Chapter not found.";
            echo json_encode($data);
            exit;
        }
        $block = $chapter->block()->first();
        if ($block->facility != $facility->id) {
            $data['status'] = "error";
            $data['msg'] = "Access denied.";
            echo json_encode($data);
            exit;
        }

        $user = User::where('cid', $cid)->first();
        if ($user == null || empty($user)) {
            $data['status'] = "error";
            $data['msg'] = "User not found or not specified";
            echo json_encode($data);
            exit;
        }

        if (TrainingProgress::where('cid', $cid)->where('chapterid', $chapterid)->count()) {
            $data['status'] = "error";
            $data['msg'] = "Already completed";
            echo json_encode($data);
            exit;
        }

        if (!$request->has('test')) {
            $progress = new TrainingProgress();
            $progress->cid = $cid;
            $progress->chapterid = $chapterid;
            $progress->date = \DB::raw('NOW()');
            $progress->save();
        }
        $data['status'] = "success";
        echo json_encode($data);
        exit;
    }
}