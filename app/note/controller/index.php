<?php
namespace Note\Controller;


class Index extends \App\Application
{

    private $_noteService;
    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        $this->_noteService = \Lsf\Loader::service('Note', false, APP_NAME_NOTE);
    }

    /**
     * 笔记分析
     * @param  void
     * @return void
     */
    public function analysis(){
        echo 'why';exit();
        $text = 'Today was one of those golden days I’ll tuck away in my heart forever. It started early—6:30 a.m.—with my six-year-old, Lily, shaking my shoulder, whispering, \'Mom, the sun’s up! Can we go to the park like you promised?\' Her eyes sparkled with that mix of sleepiness and excitement only kids possess. I said yes before my brain fully caught up, and by 8 a.m., we were at Meadowbrook Park, picnic basket in hand, dew still clinging to the grass.\n\nWe didn’t have a plan, just time. We fed ducks (Lily insisted on naming each one—Quackers, Flufftail, Sir Waddles), skipped stones across the pond (she beat me 7–2!), and built a lopsided sandcastle that she declared \'the palace of Queen Lily the Brave.\' Around noon, we spread our blanket under an oak tree and shared peanut butter sandwiches and apple slices. She told me about her dream last night—flying on a dragon made of rainbows—and I realized how rarely I truly listen without checking my phone or mentally drafting emails.\n\nAfter lunch, we joined a free nature walk led by a park ranger. Lily asked endless questions: \'Why do squirrels bury nuts?\' \'Do trees get lonely?\' The ranger smiled and said, \'You’ve got the curiosity of a scientist!\' Her pride was palpable. On the way home, she fell asleep in the car, cheek smudged with dirt, hair tangled with leaves. I carried her inside, her weight familiar and fleeting.\n\nTonight, as I washed paint-stained clothes (we’d stopped at the community art tent for finger-painting), I felt a deep calm. No screens, no schedules—just presence. I remembered how she hugged me tight after finding a four-leaf clover: \'This is for you, Mommy, because you’re my lucky day.\' In a world of deadlines and distractions, today reminded me that joy lives in the small, unplanned moments. I resolved to protect these pockets of slowness. Childhood isn’t waiting for \'someday\'; it’s happening now, in sticky fingers and whispered secrets. Tomorrow, I’ll say \'yes\' again—even if it’s raining.';
        $promptMessage = $this->_noteService->doAnalyzeNotesTasks($uid=1, $noteId=1,$text);
        exit();
    }
}
