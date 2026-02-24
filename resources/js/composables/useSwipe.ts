export function useSwipe(onSwipeLeft: () => void, onSwipeRight: () => void, threshold = 50) {
    let startX = 0;

    function onTouchStart(e: TouchEvent) {
        startX = e.touches[0].clientX;
    }

    function onTouchEnd(e: TouchEvent) {
        const delta = e.changedTouches[0].clientX - startX;
        if (Math.abs(delta) >= threshold) {
            if (delta > 0) {
                onSwipeRight();
            } else {
                onSwipeLeft();
            }
        }
    }

    return { onTouchStart, onTouchEnd };
}
