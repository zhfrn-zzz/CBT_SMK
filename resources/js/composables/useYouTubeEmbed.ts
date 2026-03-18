import { computed } from 'vue';

export function useYouTubeEmbed(url: string | null) {
    const videoId = computed(() => {
        if (!url) return null;
        const regex = /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
        const match = url.match(regex);
        return match ? match[1] : null;
    });

    const embedUrl = computed(() =>
        videoId.value ? `https://www.youtube.com/embed/${videoId.value}` : null,
    );

    const thumbnailUrl = computed(() =>
        videoId.value ? `https://img.youtube.com/vi/${videoId.value}/hqdefault.jpg` : null,
    );

    return { videoId, embedUrl, thumbnailUrl };
}
