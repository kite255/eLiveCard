<x-filament-panels::page>
    @php
        /*
        |--------------------------------------------------------------------------
        | Template Image URL
        |--------------------------------------------------------------------------
        | Support old and new upload columns automatically.
        | The saved value may be:
        | - events/1/card-templates/file.jpg
        | - public/events/1/card-templates/file.jpg
        | - storage/events/1/card-templates/file.jpg
        | - a full https URL
        */
        $templatePath = $template->template_image
            ?? $template->template_path
            ?? $template->image_path
            ?? $template->file_path
            ?? $template->background_image
            ?? null;

        $imageUrl = null;

        if (filled($templatePath)) {
            $templatePath = trim((string) $templatePath);

            if (str_starts_with($templatePath, 'http://') || str_starts_with($templatePath, 'https://') || str_starts_with($templatePath, 'data:image/')) {
                $imageUrl = $templatePath;
            } else {
                $templatePath = ltrim($templatePath, '/');

                if (str_starts_with($templatePath, 'public/')) {
                    $templatePath = substr($templatePath, 7);
                }

                if (str_starts_with($templatePath, 'storage/')) {
                    $imageUrl = asset($templatePath);
                } else {
                    $imageUrl = asset('storage/' . $templatePath);
                }
            }
        }

        /*
        | Keep the real uploaded template size for accurate placeholder percentages.
        | If width/height are missing, use the common eLive portrait size.
        */
        $templateWidth = $template->width ?: 1080;
        $templateHeight = $template->height ?: 1920;

        /*
        |--------------------------------------------------------------------------
        | Actual Sample QR Code
        |--------------------------------------------------------------------------
        | This comes from CardTemplateDesigner.php:
        | public ?string $sampleQrCodeUrl = null;
        |
        | It uses a real invitee QR code from the same event.
        */
        $defaultSampleQrUrl = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0nVVRGLTgnPz4KPHN2ZyB3aWR0aD0iMzdtbSIgaGVpZ2h0PSIzN21tIiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAzNyAzNyIgY2xhc3M9ImVsaXZlLXNhbXBsZS1xciIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMCwwSDFWMUgwek0xLDBIMlYxSDF6TTIsMEgzVjFIMnpNMywwSDRWMUgzek00LDBINVYxSDR6TTUsMEg2VjFINXpNNiwwSDdWMUg2ek05LDBIMTBWMUg5ek0xMCwwSDExVjFIMTB6TTExLDBIMTJWMUgxMXpNMTIsMEgxM1YxSDEyek0xNSwwSDE2VjFIMTV6TTE3LDBIMThWMUgxN3pNMTgsMEgxOVYxSDE4ek0yMSwwSDIyVjFIMjF6TTIyLDBIMjNWMUgyMnpNMjUsMEgyNlYxSDI1ek0yNiwwSDI3VjFIMjZ6TTI3LDBIMjhWMUgyN3pNMjgsMEgyOVYxSDI4ek0zMCwwSDMxVjFIMzB6TTMxLDBIMzJWMUgzMXpNMzIsMEgzM1YxSDMyek0zMywwSDM0VjFIMzN6TTM0LDBIMzVWMUgzNHpNMzUsMEgzNlYxSDM1ek0zNiwwSDM3VjFIMzZ6TTAsMUgxVjJIMHpNNiwxSDdWMkg2ek0xMCwxSDExVjJIMTB6TTExLDFIMTJWMkgxMXpNMTQsMUgxNVYySDE0ek0xNSwxSDE2VjJIMTV6TTE2LDFIMTdWMkgxNnpNMTcsMUgxOFYySDE3ek0xOCwxSDE5VjJIMTh6TTE5LDFIMjBWMkgxOXpNMjMsMUgyNFYySDIzek0yOCwxSDI5VjJIMjh6TTMwLDFIMzFWMkgzMHpNMzYsMUgzN1YySDM2ek0wLDJIMVYzSDB6TTIsMkgzVjNIMnpNMywySDRWM0gzek00LDJINVYzSDR6TTYsMkg3VjNINnpNMTAsMkgxMVYzSDEwek0xNCwySDE1VjNIMTR6TTE5LDJIMjBWM0gxOXpNMjAsMkgyMVYzSDIwek0yMywySDI0VjNIMjN6TTI0LDJIMjVWM0gyNHpNMjYsMkgyN1YzSDI2ek0yOCwySDI5VjNIMjh6TTMwLDJIMzFWM0gzMHpNMzIsMkgzM1YzSDMyek0zMywySDM0VjNIMzN6TTM0LDJIMzVWM0gzNHpNMzYsMkgzN1YzSDM2ek0wLDNIMVY0SDB6TTIsM0gzVjRIMnpNMywzSDRWNEgzek00LDNINVY0SDR6TTYsM0g3VjRINnpNMTEsM0gxMlY0SDExek0xMiwzSDEzVjRIMTJ6TTE0LDNIMTVWNEgxNHpNMTcsM0gxOFY0SDE3ek0yMCwzSDIxVjRIMjB6TTIyLDNIMjNWNEgyMnpNMjMsM0gyNFY0SDIzek0yNiwzSDI3VjRIMjZ6TTMwLDNIMzFWNEgzMHpNMzIsM0gzM1Y0SDMyek0zMywzSDM0VjRIMzN6TTM0LDNIMzVWNEgzNHpNMzYsM0gzN1Y0SDM2ek0wLDRIMVY1SDB6TTIsNEgzVjVIMnpNMyw0SDRWNUgzek00LDRINVY1SDR6TTYsNEg3VjVINnpNOSw0SDEwVjVIOXpNMTEsNEgxMlY1SDExek0xMiw0SDEzVjVIMTJ6TTEzLDRIMTRWNUgxM3pNMTQsNEgxNVY1SDE0ek0xNSw0SDE2VjVIMTV6TTE4LDRIMTlWNUgxOHpNMTksNEgyMFY1SDE5ek0yMCw0SDIxVjVIMjB6TTIzLDRIMjRWNUgyM3pNMjQsNEgyNVY1SDI0ek0yNSw0SDI2VjVIMjV6TTI3LDRIMjhWNUgyN3pNMjgsNEgyOVY1SDI4ek0zMCw0SDMxVjVIMzB6TTMyLDRIMzNWNUgzMnpNMzMsNEgzNFY1SDMzek0zNCw0SDM1VjVIMzR6TTM2LDRIMzdWNUgzNnpNMCw1SDFWNkgwek02LDVIN1Y2SDZ6TTgsNUg5VjZIOHpNOSw1SDEwVjZIOXpNMTAsNUgxMVY2SDEwek0xMyw1SDE0VjZIMTN6TTIwLDVIMjFWNkgyMHpNMjEsNUgyMlY2SDIxek0yNiw1SDI3VjZIMjZ6TTI3LDVIMjhWNkgyN3pNMzAsNUgzMVY2SDMwek0zNiw1SDM3VjZIMzZ6TTAsNkgxVjdIMHpNMSw2SDJWN0gxek0yLDZIM1Y3SDJ6TTMsNkg0VjdIM3pNNCw2SDVWN0g0ek01LDZINlY3SDV6TTYsNkg3VjdINnpNOCw2SDlWN0g4ek0xMCw2SDExVjdIMTB6TTEyLDZIMTNWN0gxMnpNMTQsNkgxNVY3SDE0ek0xNiw2SDE3VjdIMTZ6TTE4LDZIMTlWN0gxOHpNMjAsNkgyMVY3SDIwek0yMiw2SDIzVjdIMjJ6TTI0LDZIMjVWN0gyNHpNMjYsNkgyN1Y3SDI2ek0yOCw2SDI5VjdIMjh6TTMwLDZIMzFWN0gzMHpNMzEsNkgzMlY3SDMxek0zMiw2SDMzVjdIMzJ6TTMzLDZIMzRWN0gzM3pNMzQsNkgzNVY3SDM0ek0zNSw2SDM2VjdIMzV6TTM2LDZIMzdWN0gzNnpNMTQsN0gxNVY4SDE0ek0xNiw3SDE3VjhIMTZ6TTE3LDdIMThWOEgxN3pNMTgsN0gxOVY4SDE4ek0yMSw3SDIyVjhIMjF6TTIyLDdIMjNWOEgyMnpNMjYsN0gyN1Y4SDI2ek0yNyw3SDI4VjhIMjd6TTAsOEgxVjlIMHpNMyw4SDRWOUgzek01LDhINlY5SDV6TTYsOEg3VjlINnpNOCw4SDlWOUg4ek0xMCw4SDExVjlIMTB6TTExLDhIMTJWOUgxMXpNMTIsOEgxM1Y5SDEyek0xNiw4SDE3VjlIMTZ6TTE3LDhIMThWOUgxN3pNMTgsOEgxOVY5SDE4ek0xOSw4SDIwVjlIMTl6TTIwLDhIMjFWOUgyMHpNMjUsOEgyNlY5SDI1ek0yNiw4SDI3VjlIMjZ6TTI3LDhIMjhWOUgyN3pNMjksOEgzMFY5SDI5ek0zMSw4SDMyVjlIMzF6TTEsOUgyVjEwSDF6TTIsOUgzVjEwSDJ6TTMsOUg0VjEwSDN6TTQsOUg1VjEwSDR6TTUsOUg2VjEwSDV6TTcsOUg4VjEwSDd6TTExLDlIMTJWMTBIMTF6TTEyLDlIMTNWMTBIMTJ6TTE1LDlIMTZWMTBIMTV6TTE2LDlIMTdWMTBIMTZ6TTE4LDlIMTlWMTBIMTh6TTE5LDlIMjBWMTBIMTl6TTIzLDlIMjRWMTBIMjN6TTI1LDlIMjZWMTBIMjV6TTI2LDlIMjdWMTBIMjZ6TTI3LDlIMjhWMTBIMjd6TTMxLDlIMzJWMTBIMzF6TTMyLDlIMzNWMTBIMzJ6TTMzLDlIMzRWMTBIMzN6TTM0LDlIMzVWMTBIMzR6TTM2LDlIMzdWMTBIMzZ6TTMsMTBINFYxMUgzek01LDEwSDZWMTFINXpNNiwxMEg3VjExSDZ6TTgsMTBIOVYxMUg4ek0xMCwxMEgxMVYxMUgxMHpNMTIsMTBIMTNWMTFIMTJ6TTE0LDEwSDE1VjExSDE0ek0xNSwxMEgxNlYxMUgxNXpNMTgsMTBIMTlWMTFIMTh6TTE5LDEwSDIwVjExSDE5ek0yNywxMEgyOFYxMUgyN3pNMzAsMTBIMzFWMTFIMzB6TTMxLDEwSDMyVjExSDMxek0zNSwxMEgzNlYxMUgzNXpNMzYsMTBIMzdWMTFIMzZ6TTIsMTFIM1YxMkgyek00LDExSDVWMTJINHpNNSwxMUg2VjEySDV6TTksMTFIMTBWMTJIOXpNMTYsMTFIMTdWMTJIMTZ6TTE3LDExSDE4VjEySDE3ek0xOSwxMUgyMFYxMkgxOXpNMjAsMTFIMjFWMTJIMjB6TTIxLDExSDIyVjEySDIxek0yMiwxMUgyM1YxMkgyMnpNMjUsMTFIMjZWMTJIMjV6TTI2LDExSDI3VjEySDI2ek0yNywxMUgyOFYxMkgyN3pNMjksMTFIMzBWMTJIMjl6TTMwLDExSDMxVjEySDMwek0yLDEySDNWMTNIMnpNMywxMkg0VjEzSDN6TTQsMTJINVYxM0g0ek01LDEySDZWMTNINXpNNiwxMkg3VjEzSDZ6TTcsMTJIOFYxM0g3ek04LDEySDlWMTNIOHpNMTUsMTJIMTZWMTNIMTV6TTE2LDEySDE3VjEzSDE2ek0xNywxMkgxOFYxM0gxN3pNMjAsMTJIMjFWMTNIMjB6TTIxLDEySDIyVjEzSDIxek0yMiwxMkgyM1YxM0gyMnpNMjUsMTJIMjZWMTNIMjV6TTI3LDEySDI4VjEzSDI3ek0yOCwxMkgyOVYxM0gyOHpNMjksMTJIMzBWMTNIMjl6TTMwLDEySDMxVjEzSDMwek0zMywxMkgzNFYxM0gzM3pNMzQsMTJIMzVWMTNIMzR6TTM1LDEySDM2VjEzSDM1ek0zNiwxMkgzN1YxM0gzNnpNMCwxM0gxVjE0SDB6TTksMTNIMTBWMTRIOXpNMTAsMTNIMTFWMTRIMTB6TTEzLDEzSDE0VjE0SDEzek0xNCwxM0gxNVYxNEgxNHpNMTUsMTNIMTZWMTRIMTV6TTE4LDEzSDE5VjE0SDE4ek0xOSwxM0gyMFYxNEgxOXpNMjEsMTNIMjJWMTRIMjF6TTIyLDEzSDIzVjE0SDIyek0yNCwxM0gyNVYxNEgyNHpNMjUsMTNIMjZWMTRIMjV6TTI5LDEzSDMwVjE0SDI5ek0zMCwxM0gzMVYxNEgzMHpNMzEsMTNIMzJWMTRIMzF6TTM0LDEzSDM1VjE0SDM0ek0wLDE0SDFWMTVIMHpNMiwxNEgzVjE1SDJ6TTQsMTRINVYxNUg0ek02LDE0SDdWMTVINnpNNywxNEg4VjE1SDd6TTksMTRIMTBWMTVIOXpNMTAsMTRIMTFWMTVIMTB6TTExLDE0SDEyVjE1SDExek0xMywxNEgxNFYxNUgxM3pNMTYsMTRIMTdWMTVIMTZ6TTE5LDE0SDIwVjE1SDE5ek0yMCwxNEgyMVYxNUgyMHpNMjIsMTRIMjNWMTVIMjJ6TTIzLDE0SDI0VjE1SDIzek0yNCwxNEgyNVYxNUgyNHpNMjksMTRIMzBWMTVIMjl6TTMxLDE0SDMyVjE1SDMxek0zMiwxNEgzM1YxNUgzMnpNMzQsMTRIMzVWMTVIMzR6TTAsMTVIMVYxNkgwek0yLDE1SDNWMTZIMnpNMywxNUg0VjE2SDN6TTUsMTVINlYxNkg1ek05LDE1SDEwVjE2SDl6TTEwLDE1SDExVjE2SDEwek0xMywxNUgxNFYxNkgxM3pNMTQsMTVIMTVWMTZIMTR6TTE1LDE1SDE2VjE2SDE1ek0yMCwxNUgyMVYxNkgyMHpNMjEsMTVIMjJWMTZIMjF6TTIzLDE1SDI0VjE2SDIzek0yNCwxNUgyNVYxNkgyNHpNMjYsMTVIMjdWMTZIMjZ6TTI3LDE1SDI4VjE2SDI3ek0yOCwxNUgyOVYxNkgyOHpNMzIsMTVIMzNWMTZIMzJ6TTM1LDE1SDM2VjE2SDM1ek0wLDE2SDFWMTdIMHpNMSwxNkgyVjE3SDF6TTIsMTZIM1YxN0gyek0zLDE2SDRWMTdIM3pNNCwxNkg1VjE3SDR6TTYsMTZIN1YxN0g2ek03LDE2SDhWMTdIN3pNOSwxNkgxMFYxN0g5ek0xMywxNkgxNFYxN0gxM3pNMTQsMTZIMTVWMTdIMTR6TTE2LDE2SDE3VjE3SDE2ek0xOSwxNkgyMFYxN0gxOXpNMjAsMTZIMjFWMTdIMjB6TTI0LDE2SDI1VjE3SDI0ek0zMSwxNkgzMlYxN0gzMXpNMzIsMTZIMzNWMTdIMzJ6TTMzLDE2SDM0VjE3SDMzek0zNCwxNkgzNVYxN0gzNHpNMzYsMTZIMzdWMTdIMzZ6TTAsMTdIMVYxOEgwek0zLDE3SDRWMThIM3pNOCwxN0g5VjE4SDh6TTksMTdIMTBWMThIOXpNMTEsMTdIMTJWMThIMTF6TTEzLDE3SDE0VjE4SDEzek0xNSwxN0gxNlYxOEgxNXpNMTksMTdIMjBWMThIMTl6TTIxLDE3SDIyVjE4SDIxek0yNCwxN0gyNVYxOEgyNHpNMjUsMTdIMjZWMThIMjV6TTI5LDE3SDMwVjE4SDI5ek0zMCwxN0gzMVYxOEgzMHpNMzIsMTdIMzNWMThIMzJ6TTMzLDE3SDM0VjE4SDMzek0zNCwxN0gzNVYxOEgzNHpNMzUsMTdIMzZWMThIMzV6TTM2LDE3SDM3VjE4SDM2ek0wLDE4SDFWMTlIMHpNMywxOEg0VjE5SDN6TTQsMThINVYxOUg0ek02LDE4SDdWMTlINnpNOCwxOEg5VjE5SDh6TTEwLDE4SDExVjE5SDEwek0xMiwxOEgxM1YxOUgxMnpNMTMsMThIMTRWMTlIMTN6TTE0LDE4SDE1VjE5SDE0ek0xOSwxOEgyMFYxOUgxOXpNMjAsMThIMjFWMTlIMjB6TTIyLDE4SDIzVjE5SDIyek0yMywxOEgyNFYxOUgyM3pNMjQsMThIMjVWMTlIMjR6TTMwLDE4SDMxVjE5SDMwek0zMSwxOEgzMlYxOUgzMXpNMzUsMThIMzZWMTlIMzV6TTM2LDE4SDM3VjE5SDM2ek0wLDE5SDFWMjBIMHpNMSwxOUgyVjIwSDF6TTMsMTlINFYyMEgzek00LDE5SDVWMjBINHpNNywxOUg4VjIwSDd6TTEwLDE5SDExVjIwSDEwek0xMywxOUgxNFYyMEgxM3pNMTUsMTlIMTZWMjBIMTV6TTE2LDE5SDE3VjIwSDE2ek0xOSwxOUgyMFYyMEgxOXpNMjAsMTlIMjFWMjBIMjB6TTIzLDE5SDI0VjIwSDIzek0yNiwxOUgyN1YyMEgyNnpNMjcsMTlIMjhWMjBIMjd6TTMxLDE5SDMyVjIwSDMxek0zMiwxOUgzM1YyMEgzMnpNMzQsMTlIMzVWMjBIMzR6TTM1LDE5SDM2VjIwSDM1ek0wLDIwSDFWMjFIMHpNMSwyMEgyVjIxSDF6TTQsMjBINVYyMUg0ek01LDIwSDZWMjFINXpNNiwyMEg3VjIxSDZ6TTcsMjBIOFYyMUg3ek04LDIwSDlWMjFIOHpNOSwyMEgxMFYyMUg5ek0xMCwyMEgxMVYyMUgxMHpNMTMsMjBIMTRWMjFIMTN6TTE2LDIwSDE3VjIxSDE2ek0xOCwyMEgxOVYyMUgxOHpNMTksMjBIMjBWMjFIMTl6TTIzLDIwSDI0VjIxSDIzek0yNSwyMEgyNlYyMUgyNXpNMjgsMjBIMjlWMjFIMjh6TTMwLDIwSDMxVjIxSDMwek0zMiwyMEgzM1YyMUgzMnpNMzQsMjBIMzVWMjFIMzR6TTM1LDIwSDM2VjIxSDM1ek0xLDIxSDJWMjJIMXpNMywyMUg0VjIySDN6TTQsMjFINVYyMkg0ek04LDIxSDlWMjJIOHpNMTAsMjFIMTFWMjJIMTB6TTE1LDIxSDE2VjIySDE1ek0xNywyMUgxOFYyMkgxN3pNMTksMjFIMjBWMjJIMTl6TTIyLDIxSDIzVjIySDIyek0yNCwyMUgyNVYyMkgyNHpNMjgsMjFIMjlWMjJIMjh6TTMwLDIxSDMxVjIySDMwek0zMSwyMUgzMlYyMkgzMXpNMzMsMjFIMzRWMjJIMzN6TTM1LDIxSDM2VjIySDM1ek0zNiwyMUgzN1YyMkgzNnpNMSwyMkgyVjIzSDF6TTIsMjJIM1YyM0gyek02LDIySDdWMjNINnpNNywyMkg4VjIzSDd6TTgsMjJIOVYyM0g4ek05LDIySDEwVjIzSDl6TTExLDIySDEyVjIzSDExek0xMiwyMkgxM1YyM0gxMnpNMTMsMjJIMTRWMjNIMTN6TTE2LDIySDE3VjIzSDE2ek0xOCwyMkgxOVYyM0gxOHpNMTksMjJIMjBWMjNIMTl6TTIwLDIySDIxVjIzSDIwek0yMSwyMkgyMlYyM0gyMXpNMjIsMjJIMjNWMjNIMjJ6TTI0LDIySDI1VjIzSDI0ek0yNSwyMkgyNlYyM0gyNXpNMjYsMjJIMjdWMjNIMjZ6TTI5LDIySDMwVjIzSDI5ek0zMCwyMkgzMVYyM0gzMHpNMCwyM0gxVjI0SDB6TTIsMjNIM1YyNEgyek00LDIzSDVWMjRINHpNNywyM0g4VjI0SDd6TTEwLDIzSDExVjI0SDEwek0xMSwyM0gxMlYyNEgxMXpNMTYsMjNIMTdWMjRIMTZ6TTE4LDIzSDE5VjI0SDE4ek0yMywyM0gyNFYyNEgyM3pNMjQsMjNIMjVWMjRIMjR6TTI1LDIzSDI2VjI0SDI1ek0yOCwyM0gyOVYyNEgyOHpNMzAsMjNIMzFWMjRIMzB6TTM1LDIzSDM2VjI0SDM1ek0wLDI0SDFWMjVIMHpNMSwyNEgyVjI1SDF6TTIsMjRIM1YyNUgyek0zLDI0SDRWMjVIM3pNNSwyNEg2VjI1SDV6TTYsMjRIN1YyNUg2ek0xMCwyNEgxMVYyNUgxMHpNMTEsMjRIMTJWMjVIMTF6TTEzLDI0SDE0VjI1SDEzek0xNCwyNEgxNVYyNUgxNHpNMTYsMjRIMTdWMjVIMTZ6TTE4LDI0SDE5VjI1SDE4ek0yMywyNEgyNFYyNUgyM3pNMjQsMjRIMjVWMjVIMjR6TTI3LDI0SDI4VjI1SDI3ek0zMCwyNEgzMVYyNUgzMHpNMzEsMjRIMzJWMjVIMzF6TTMzLDI0SDM0VjI1SDMzek0zNSwyNEgzNlYyNUgzNXpNMSwyNUgyVjI2SDF6TTUsMjVINlYyNkg1ek03LDI1SDhWMjZIN3pNOSwyNUgxMFYyNkg5ek0xMCwyNUgxMVYyNkgxMHpNMTEsMjVIMTJWMjZIMTF6TTEzLDI1SDE0VjI2SDEzek0xNSwyNUgxNlYyNkgxNXpNMTcsMjVIMThWMjZIMTd6TTE4LDI1SDE5VjI2SDE4ek0yMiwyNUgyM1YyNkgyMnpNMjMsMjVIMjRWMjZIMjN6TTI0LDI1SDI1VjI2SDI0ek0yNSwyNUgyNlYyNkgyNXpNMzEsMjVIMzJWMjZIMzF6TTM0LDI1SDM1VjI2SDM0ek0zNSwyNUgzNlYyNkgzNXpNMzYsMjVIMzdWMjZIMzZ6TTAsMjZIMVYyN0gwek02LDI2SDdWMjdINnpNNywyNkg4VjI3SDd6TTgsMjZIOVYyN0g4ek0xMCwyNkgxMVYyN0gxMHpNMTQsMjZIMTVWMjdIMTR6TTE1LDI2SDE2VjI3SDE1ek0xNiwyNkgxN1YyN0gxNnpNMjAsMjZIMjFWMjdIMjB6TTIxLDI2SDIyVjI3SDIxek0yNSwyNkgyNlYyN0gyNXpNMjYsMjZIMjdWMjdIMjZ6TTI5LDI2SDMwVjI3SDI5ek0zMCwyNkgzMVYyN0gzMHpNMzIsMjZIMzNWMjdIMzJ6TTMzLDI2SDM0VjI3SDMzek0zNiwyNkgzN1YyN0gzNnpNMSwyN0gyVjI4SDF6TTIsMjdIM1YyOEgyek0zLDI3SDRWMjhIM3pNOCwyN0g5VjI4SDh6TTksMjdIMTBWMjhIOXpNMTAsMjdIMTFWMjhIMTB6TTEyLDI3SDEzVjI4SDEyek0xMywyN0gxNFYyOEgxM3pNMTQsMjdIMTVWMjhIMTR6TTE2LDI3SDE3VjI4SDE2ek0xOCwyN0gxOVYyOEgxOHpNMTksMjdIMjBWMjhIMTl6TTIyLDI3SDIzVjI4SDIyek0yMywyN0gyNFYyOEgyM3pNMjUsMjdIMjZWMjhIMjV6TTI5LDI3SDMwVjI4SDI5ek0zMCwyN0gzMVYyOEgzMHpNMzEsMjdIMzJWMjhIMzF6TTM1LDI3SDM2VjI4SDM1ek0zNiwyN0gzN1YyOEgzNnpNMCwyOEgxVjI5SDB6TTEsMjhIMlYyOUgxek0zLDI4SDRWMjlIM3pNNiwyOEg3VjI5SDZ6TTcsMjhIOFYyOUg3ek05LDI4SDEwVjI5SDl6TTE0LDI4SDE1VjI5SDE0ek0xNSwyOEgxNlYyOUgxNXpNMTYsMjhIMTdWMjlIMTZ6TTE3LDI4SDE4VjI5SDE3ek0xOSwyOEgyMFYyOUgxOXpNMjQsMjhIMjVWMjlIMjR6TTI3LDI4SDI4VjI5SDI3ek0yOCwyOEgyOVYyOUgyOHpNMjksMjhIMzBWMjlIMjl6TTMwLDI4SDMxVjI5SDMwek0zMSwyOEgzMlYyOUgzMXpNMzIsMjhIMzNWMjlIMzJ6TTgsMjlIOVYzMEg4ek05LDI5SDEwVjMwSDl6TTExLDI5SDEyVjMwSDExek0xNCwyOUgxNVYzMEgxNHpNMTcsMjlIMThWMzBIMTd6TTIxLDI5SDIyVjMwSDIxek0yMiwyOUgyM1YzMEgyMnpNMjMsMjlIMjRWMzBIMjN6TTI2LDI5SDI3VjMwSDI2ek0yNywyOUgyOFYzMEgyN3pNMjgsMjlIMjlWMzBIMjh6TTMyLDI5SDMzVjMwSDMyek0zMywyOUgzNFYzMEgzM3pNMzUsMjlIMzZWMzBIMzV6TTAsMzBIMVYzMUgwek0xLDMwSDJWMzFIMXpNMiwzMEgzVjMxSDJ6TTMsMzBINFYzMUgzek00LDMwSDVWMzFINHpNNSwzMEg2VjMxSDV6TTYsMzBIN1YzMUg2ek05LDMwSDEwVjMxSDl6TTE3LDMwSDE4VjMxSDE3ek0yMCwzMEgyMVYzMUgyMHpNMjIsMzBIMjNWMzFIMjJ6TTI1LDMwSDI2VjMxSDI1ek0yNiwzMEgyN1YzMUgyNnpNMjcsMzBIMjhWMzFIMjd6TTI4LDMwSDI5VjMxSDI4ek0zMCwzMEgzMVYzMUgzMHpNMzIsMzBIMzNWMzFIMzJ6TTMzLDMwSDM0VjMxSDMzek0zNSwzMEgzNlYzMUgzNXpNMCwzMUgxVjMySDB6TTYsMzFIN1YzMkg2ek04LDMxSDlWMzJIOHpNOSwzMUgxMFYzMkg5ek0xMCwzMUgxMVYzMkgxMHpNMTIsMzFIMTNWMzJIMTJ6TTIxLDMxSDIyVjMySDIxek0yNCwzMUgyNVYzMkgyNHpNMjcsMzFIMjhWMzJIMjd6TTI4LDMxSDI5VjMySDI4ek0zMiwzMUgzM1YzMkgzMnpNMzMsMzFIMzRWMzJIMzN6TTM0LDMxSDM1VjMySDM0ek0zNSwzMUgzNlYzMkgzNXpNMCwzMkgxVjMzSDB6TTIsMzJIM1YzM0gyek0zLDMySDRWMzNIM3pNNCwzMkg1VjMzSDR6TTYsMzJIN1YzM0g2ek0xMCwzMkgxMVYzM0gxMHpNMTMsMzJIMTRWMzNIMTN6TTE0LDMySDE1VjMzSDE0ek0xNSwzMkgxNlYzM0gxNXpNMTcsMzJIMThWMzNIMTd6TTE5LDMySDIwVjMzSDE5ek0yMiwzMkgyM1YzM0gyMnpNMjQsMzJIMjVWMzNIMjR6TTI1LDMySDI2VjMzSDI1ek0yOCwzMkgyOVYzM0gyOHpNMjksMzJIMzBWMzNIMjl6TTMwLDMySDMxVjMzSDMwek0zMSwzMkgzMlYzM0gzMXpNMzIsMzJIMzNWMzNIMzJ6TTMzLDMySDM0VjMzSDMzek0zNiwzMkgzN1YzM0gzNnpNMCwzM0gxVjM0SDB6TTIsMzNIM1YzNEgyek0zLDMzSDRWMzRIM3pNNCwzM0g1VjM0SDR6TTYsMzNIN1YzNEg2ek04LDMzSDlWMzRIOHpNOSwzM0gxMFYzNEg5ek0xMCwzM0gxMVYzNEgxMHpNMTIsMzNIMTNWMzRIMTJ6TTEzLDMzSDE0VjM0SDEzek0xNSwzM0gxNlYzNEgxNXpNMTcsMzNIMThWMzRIMTd6TTE5LDMzSDIwVjM0SDE5ek0yMCwzM0gyMVYzNEgyMHpNMjIsMzNIMjNWMzRIMjJ6TTI1LDMzSDI2VjM0SDI1ek0yNiwzM0gyN1YzNEgyNnpNMjcsMzNIMjhWMzRIMjd6TTMxLDMzSDMyVjM0SDMxek0zMiwzM0gzM1YzNEgzMnpNMzMsMzNIMzRWMzRIMzN6TTM0LDMzSDM1VjM0SDM0ek0wLDM0SDFWMzVIMHpNMiwzNEgzVjM1SDJ6TTMsMzRINFYzNUgzek00LDM0SDVWMzVINHpNNiwzNEg3VjM1SDZ6TTksMzRIMTBWMzVIOXpNMTIsMzRIMTNWMzVIMTJ6TTEzLDM0SDE0VjM1SDEzek0xNiwzNEgxN1YzNUgxNnpNMjAsMzRIMjFWMzVIMjB6TTIyLDM0SDIzVjM1SDIyek0yNiwzNEgyN1YzNUgyNnpNMjcsMzRIMjhWMzVIMjd6TTMxLDM0SDMyVjM1SDMxek0zMiwzNEgzM1YzNUgzMnpNMzQsMzRIMzVWMzVIMzR6TTM2LDM0SDM3VjM1SDM2ek0wLDM1SDFWMzZIMHpNNiwzNUg3VjM2SDZ6TTEwLDM1SDExVjM2SDEwek0xMiwzNUgxM1YzNkgxMnpNMTMsMzVIMTRWMzZIMTN6TTE1LDM1SDE2VjM2SDE1ek0xOCwzNUgxOVYzNkgxOHpNMjEsMzVIMjJWMzZIMjF6TTIyLDM1SDIzVjM2SDIyek0yNCwzNUgyNVYzNkgyNHpNMjUsMzVIMjZWMzZIMjV6TTI3LDM1SDI4VjM2SDI3ek0yOSwzNUgzMFYzNkgyOXpNMzEsMzVIMzJWMzZIMzF6TTM0LDM1SDM1VjM2SDM0ek0wLDM2SDFWMzdIMHpNMSwzNkgyVjM3SDF6TTIsMzZIM1YzN0gyek0zLDM2SDRWMzdIM3pNNCwzNkg1VjM3SDR6TTUsMzZINlYzN0g1ek02LDM2SDdWMzdINnpNOCwzNkg5VjM3SDh6TTksMzZIMTBWMzdIOXpNMTMsMzZIMTRWMzdIMTN6TTE0LDM2SDE1VjM3SDE0ek0xNiwzNkgxN1YzN0gxNnpNMTcsMzZIMThWMzdIMTd6TTE5LDM2SDIwVjM3SDE5ek0yMSwzNkgyMlYzN0gyMXpNMjQsMzZIMjVWMzdIMjR6TTI1LDM2SDI2VjM3SDI1ek0yNywzNkgyOFYzN0gyN3pNMjgsMzZIMjlWMzdIMjh6TTI5LDM2SDMwVjM3SDI5ek0zMCwzNkgzMVYzN0gzMHpNMzUsMzZIMzZWMzdIMzV6TTM2LDM2SDM3VjM3SDM2eiIgaWQ9InFyLXBhdGgiIGZpbGw9IiMwMDAwMDAiIGZpbGwtb3BhY2l0eT0iMSIgZmlsbC1ydWxlPSJub256ZXJvIiBzdHJva2U9Im5vbmUiLz48L3N2Zz4=';
        $actualQrUrl = $sampleQrCodeUrl ?: $defaultSampleQrUrl;
    @endphp

    <div
        x-data="simpleCardDesigner({
            placeholders: @entangle('placeholders').live,
            selectedKey: @entangle('selectedPlaceholder').live,
            zoom: @entangle('zoom').live,
            showPreview: @entangle('showPreview').live,
            templateWidth: {{ (int) $templateWidth }},
            templateHeight: {{ (int) $templateHeight }},
            sampleQrCodeUrl: @js($actualQrUrl),
            defaultSampleQrCodeUrl: @js($defaultSampleQrUrl),
        })"
        x-init="init()"
        class="elive-designer"
    >
        <div class="designer-header">
            <div>
                <div class="breadcrumb">
                    <span>Card Templates</span>
                    <span>/</span>
                    <strong>Designer</strong>
                </div>

                <h1>Card Template Designer</h1>
                <p>Drag placeholders or use direction buttons to position details exactly on the card.</p>
            </div>

            <div class="designer-actions">
                <button type="button" @click="syncToLivewire(); $wire.savePositions()" class="btn-primary">
                    Save Design
                </button>

                <button type="button" @click="syncToLivewire(); $wire.previewCard()" class="btn-outline cyan">
                    Preview
                </button>

                <button type="button" wire:click="resetPositions" class="btn-outline">
                    Reset
                </button>

                <a href="{{ \App\Filament\Resources\CardTemplateResource::getUrl('index') }}" class="btn-outline">
                    Back
                </a>
            </div>
        </div>

        <div class="template-summary">
            <div class="template-thumb">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $template->name }}">
                @else
                    <div class="empty-thumb">No Image</div>
                @endif
            </div>

            <div>
                <span>Template</span>
                <strong>{{ $template->name }}</strong>
            </div>

            <div>
                <span>Event</span>
                <strong>{{ $template->event?->title ?? 'No event selected' }}</strong>
            </div>

            <div>
                <span>Status</span>
                <strong class="status-badge">{{ ucfirst($template->status) }}</strong>
            </div>

            <div>
                <span>Size</span>
                <strong>{{ $templateWidth }} × {{ $templateHeight }} px</strong>
            </div>
        </div>

        <div class="designer-layout">
            <div class="canvas-panel">
                <div class="canvas-toolbar">
                    <button type="button" class="tool-btn" @click="fitToScreen">Fit</button>
                    <button type="button" class="tool-btn" @click="zoom = 50">50%</button>
                    <button type="button" class="tool-btn" @click="zoom = 75">75%</button>
                    <button type="button" class="tool-btn" @click="zoom = 100">100%</button>

                    <div class="zoom-control">
                        <button type="button" @click="decreaseZoom">−</button>
                        <input type="range" min="25" max="160" step="5" x-model.number="zoom">
                        <button type="button" @click="increaseZoom">+</button>
                    </div>

                    <span class="zoom-label" x-text="zoom + '%'"></span>
                </div>

                <div class="workspace" x-ref="workspace">
                    <div class="canvas-scroll">
                        <div
                            class="canvas-scale-box"
                            :style="{
                                width: `${templateWidth * zoom / 100}px`,
                                height: `${templateHeight * zoom / 100}px`
                            }"
                        >
                            <div
                                class="card-canvas"
                                tabindex="0"
                                :style="{
                                    width: '{{ $templateWidth }}px',
                                    height: '{{ $templateHeight }}px',
                                    transform: `scale(${zoom / 100})`
                                }"
                                @click="selectedKey = null"
                                @keydown.arrow-up.prevent="moveSelected('up', $event.shiftKey)"
                                @keydown.arrow-down.prevent="moveSelected('down', $event.shiftKey)"
                                @keydown.arrow-left.prevent="moveSelected('left', $event.shiftKey)"
                                @keydown.arrow-right.prevent="moveSelected('right', $event.shiftKey)"
                            >
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" class="template-image" alt="Template">
                            @else
                                <div class="template-placeholder-bg">
                                    Upload a template image first
                                </div>
                            @endif

                            <template x-for="(placeholder, key) in placeholders" :key="key">
                                <div
                                    x-show="placeholder.is_visible"
                                    class="placeholder-box"
                                    :class="{
                                        selected: selectedKey === key,
                                        qr: isQr(placeholder),
                                    }"
                                    :style="placeholderStyle(placeholder)"
                                    @mousedown.stop="startDrag($event, key)"
                                    @click.stop="selectPlaceholder(key)"
                                >
                                    <template x-if="isQr(placeholder)">
                                        <div
                                            class="qr-preview actual-qr"
                                            :style="{
                                                backgroundColor: placeholder.qr_background_color || '#ffffff'
                                            }"
                                        >
                                            <template x-if="sampleQrCodeUrl">
                                                <img :src="sampleQrCodeUrl" alt="Actual QR Code Preview">
                                            </template>

                                            <template x-if="!sampleQrCodeUrl">
                                                <img :src="defaultSampleQrCodeUrl" alt="Sample QR Code Preview">
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="! isQr(placeholder)">
                                        <span x-text="previewValue(placeholder)"></span>
                                    </template>

                                    <template x-if="selectedKey === key">
                                        <span class="resize-handle" @mousedown.stop="startResize($event, key)"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                        </div>
                    </div>
                </div>

                <div class="designer-note">
                    Drag placeholders, resize using the corner handle, or use direction buttons for precise movement.
                </div>
            </div>

            <div class="simple-settings-panel">
                <div class="simple-panel-section">
                    <h3>Add Placeholder</h3>
                    <p>Choose the fields you want to appear on this card.</p>

                    <div class="simple-picker-grid">
                        @foreach (\App\Models\CardTemplatePlaceholder::availablePlaceholders() as $key => $label)
                            <button
                                type="button"
                                wire:click="addPlaceholder('{{ $key }}')"
                                @click="selectedKey = '{{ $key }}'"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="simple-panel-section" x-show="current" x-cloak>
                    <h3>Selected Placeholder</h3>
                    <p>Adjust the selected item.</p>

                    <div class="selected-card">
                        <strong x-text="current?.label"></strong>

                        <label>
                            <span>Show on card</span>
                            <input type="checkbox" x-model="current.is_visible">
                        </label>
                    </div>

                    <div class="direction-control">
                        <button type="button" @click="moveSelected('up', $event.shiftKey)">↑</button>

                        <div>
                            <button type="button" @click="moveSelected('left', $event.shiftKey)">←</button>
                            <button type="button" @click="moveSelected('down', $event.shiftKey)">↓</button>
                            <button type="button" @click="moveSelected('right', $event.shiftKey)">→</button>
                        </div>

                        <small>Click to move. Shift + click moves faster.</small>
                    </div>

                    <div class="simple-form-grid">
                        <label>
                            X %
                            <input type="number" min="0" max="100" step="0.1" x-model.number="current.x_percent">
                        </label>

                        <label>
                            Y %
                            <input type="number" min="0" max="100" step="0.1" x-model.number="current.y_percent">
                        </label>

                        <label>
                            Width %
                            <input type="number" min="1" max="100" step="0.1" x-model.number="current.width_percent">
                        </label>

                        <label>
                            Height %
                            <input type="number" min="1" max="100" step="0.1" x-model.number="current.height_percent">
                        </label>

                        <template x-if="! isQr(current)">
                            <label>
                                Font Family
                                <select x-model="current.font_family">
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Lexend">Lexend</option>
                                    <option value="Corben">Corben</option>
                                </select>
                            </label>
                        </template>

                        <template x-if="! isQr(current)">
                            <label>
                                Font Size
                                <input type="number" min="8" max="120" x-model.number="current.font_size">
                            </label>
                        </template>

                        <template x-if="! isQr(current)">
                            <label>
                                Font Color
                                <input type="color" x-model="current.font_color">
                            </label>
                        </template>

                        <template x-if="! isQr(current)">
                            <label>
                                Font Weight
                                <select x-model="current.font_weight">
                                    <option value="normal">Normal</option>
                                    <option value="bold">Bold</option>
                                </select>
                            </label>
                        </template>

                        <template x-if="! isQr(current)">
                            <label>
                                Text Align
                                <select x-model="current.text_align">
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                </select>
                            </label>
                        </template>

                        <template x-if="isQr(current)">
                            <label>
                                QR Output Size
                                <input type="number" min="40" max="1000" x-model.number="current.qr_size">
                            </label>
                        </template>

                        <template x-if="isQr(current)">
                            <label>
                                QR Color
                                <input type="color" x-model="current.qr_color">
                            </label>
                        </template>

                        <template x-if="isQr(current)">
                            <label>
                                QR Background
                                <input type="color" x-model="current.qr_background_color">
                            </label>
                        </template>

                        <template x-if="isQr(current)">
                            <div class="qr-helper-note">
                                QR uses the same drag, resize, arrows, visibility, and save controls as the other placeholders.
                            </div>
                        </template>
                    </div>

                    <div class="panel-actions">
                        <button type="button" class="btn-small danger" @click="$wire.removePlaceholder(selectedKey)">
                            Remove
                        </button>

                        <button type="button" class="btn-small" @click="current.is_visible = ! current.is_visible">
                            Show / Hide
                        </button>
                    </div>
                </div>

                <div class="simple-panel-section">
                    <h3>Current Placeholders</h3>
                    <p>Click a placeholder to edit it.</p>

                    <div class="placeholder-list">
                        <template x-for="(placeholder, key) in placeholders" :key="key">
                            <button
                                type="button"
                                class="placeholder-list-item"
                                :class="{ active: selectedKey === key }"
                                @click="selectPlaceholder(key)"
                            >
                                <span x-text="placeholder.label"></span>
                                <small x-text="placeholder.is_visible ? 'Visible' : 'Hidden'"></small>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="preview-modal" x-show="showPreview" x-cloak>
            <div class="preview-backdrop" @click="$wire.closePreview()"></div>

            <div class="preview-card">
                <div class="preview-header">
                    <div>
                        <h3>Card Preview</h3>
                        <p>This preview uses sample invitee data and an actual generated QR code.</p>
                    </div>

                    <button type="button" @click="$wire.closePreview()">×</button>
                </div>

                <div class="preview-body">
                    <div
                        class="preview-canvas"
                        :style="{
                            width: '{{ $templateWidth }}px',
                            height: '{{ $templateHeight }}px'
                        }"
                    >
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" class="template-image" alt="Template Preview">
                        @else
                            <div class="template-placeholder-bg">
                                No template image
                            </div>
                        @endif

                        <template x-for="(placeholder, key) in placeholders" :key="'preview-' + key">
                            <div
                                x-show="placeholder.is_visible"
                                class="placeholder-box preview-mode"
                                :class="{ qr: isQr(placeholder) }"
                                :style="placeholderStyle(placeholder)"
                            >
                                <template x-if="isQr(placeholder)">
                                    <div
                                        class="qr-preview actual-qr"
                                        :style="{
                                            backgroundColor: placeholder.qr_background_color || '#ffffff'
                                        }"
                                    >
                                        <template x-if="sampleQrCodeUrl">
                                            <img :src="sampleQrCodeUrl" alt="Actual QR Code Preview">
                                        </template>

                                        <template x-if="!sampleQrCodeUrl">
                                            <img :src="defaultSampleQrCodeUrl" alt="Sample QR Code Preview">
                                        </template>
                                    </div>
                                </template>

                                <template x-if="! isQr(placeholder)">
                                    <span x-text="previewValue(placeholder)"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="preview-footer">
                    <button type="button" class="btn-primary" @click="$wire.closePreview()">
                        Close Preview
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        @font-face {
            font-family: 'Montserrat';
            src: url('/fonts/Montserrat-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Montserrat';
            src: url('/fonts/Montserrat-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        @font-face {
            font-family: 'Roboto';
            src: url('/fonts/Roboto-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Roboto';
            src: url('/fonts/Roboto-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        @font-face {
            font-family: 'Lexend';
            src: url('/fonts/Lexend-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Lexend';
            src: url('/fonts/Lexend-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        @font-face {
            font-family: 'Corben';
            src: url('/fonts/Corben-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Corben';
            src: url('/fonts/Corben-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        [x-cloak] {
            display: none !important;
        }

        .elive-designer {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .designer-header,
        .template-summary,
        .canvas-panel,
        .simple-settings-panel {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .designer-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
        }

        .breadcrumb {
            display: flex;
            gap: .5rem;
            align-items: center;
            font-size: .8rem;
            color: #64748b;
            margin-bottom: .35rem;
        }

        .designer-header h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .designer-header p {
            color: #64748b;
            margin-top: .25rem;
        }

        .designer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }

        .btn-primary,
        .btn-outline,
        .btn-small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            border-radius: .8rem;
            padding: .65rem .9rem;
            font-size: .85rem;
            font-weight: 700;
            transition: .2s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: #0f172a;
            color: white;
            border: 1px solid #0f172a;
        }

        .btn-outline {
            background: white;
            color: #0f172a;
            border: 1px solid #d1d5db;
        }

        .btn-outline.cyan {
            color: #0369a1;
            border-color: #7dd3fc;
            background: #f0f9ff;
        }

        .btn-small {
            border: 1px solid #d1d5db;
            background: #f8fafc;
            color: #0f172a;
            padding: .5rem .7rem;
        }

        .btn-small.danger {
            color: #b91c1c;
            border-color: #fecaca;
            background: #fef2f2;
        }

        .template-summary {
            display: grid;
            grid-template-columns: 80px repeat(4, minmax(0, 1fr));
            gap: 1rem;
            align-items: center;
            padding: 1rem;
        }

        .template-summary span {
            display: block;
            font-size: .75rem;
            color: #64748b;
            margin-bottom: .25rem;
        }

        .template-summary strong {
            color: #0f172a;
            font-size: .9rem;
        }

        .template-thumb {
            width: 60px;
            height: 76px;
            border-radius: .9rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .template-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center center;
            background: #ffffff;
        }

        .empty-thumb {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            color: #94a3b8;
        }

        .status-badge {
            display: inline-flex;
            padding: .25rem .55rem;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e !important;
        }

        .designer-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 1.25rem;
            align-items: start;
        }

        .canvas-panel {
            overflow: hidden;
        }

        .canvas-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .6rem;
            padding: .9rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .tool-btn {
            border: 1px solid #d1d5db;
            background: #f8fafc;
            color: #0f172a;
            border-radius: .65rem;
            padding: .45rem .65rem;
            font-size: .8rem;
            font-weight: 700;
        }

        .zoom-control {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .zoom-control button {
            width: 30px;
            height: 30px;
            border: 1px solid #d1d5db;
            border-radius: .55rem;
            background: white;
            font-weight: 800;
        }

        .zoom-label {
            font-size: .85rem;
            font-weight: 700;
            color: #334155;
        }

        .workspace {
            background:
                linear-gradient(45deg, #f1f5f9 25%, transparent 25%),
                linear-gradient(-45deg, #f1f5f9 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f1f5f9 75%),
                linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
            background-size: 22px 22px;
            background-position: 0 0, 0 11px, 11px -11px, -11px 0;
            padding: .35rem;
            min-height: calc(100vh - 260px);
            overflow: auto;
        }

        .canvas-scroll {
            width: 100%;
            min-height: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .canvas-scale-box {
            position: relative;
            flex: 0 0 auto;
        }

        .card-canvas,
        .preview-canvas {
            position: relative;
            transform-origin: top left;
            background: #ffffff;
            overflow: hidden;
            border-radius: .25rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
        }

        .template-image,
        .template-placeholder-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center center;
            display: block;
            z-index: 1;
        }

        .template-placeholder-bg {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
        }

        .placeholder-box {
            position: absolute;
            z-index: 10;
            border: 1.5px dashed rgba(14, 165, 233, .95);
            background: rgba(255, 255, 255, .42);
            color: #0f172a;
            border-radius: .45rem;
            cursor: move;
            display: flex;
            align-items: center;
            overflow: hidden;
            user-select: none;
        }

        .placeholder-box.selected {
            border: 2px solid #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, .18);
        }

        .placeholder-box.preview-mode {
            border-color: transparent;
            background: transparent;
            box-shadow: none;
            cursor: default;
        }

        .placeholder-box span {
            width: 100%;
            padding: .15rem .35rem;
            line-height: 1.1;
        }

        .placeholder-box.qr {
            align-items: center;
            justify-content: center;
            padding: 0;
            background: transparent;
        }

        .qr-preview {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: #ffffff;
            border: none;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
        }

        .qr-preview.actual-qr img,
        .qr-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #ffffff;
            padding: 3%;
            box-sizing: border-box;
            image-rendering: auto;
            pointer-events: none;
            user-select: none;
        }

        .qr-fallback {
            width: 100%;
            height: 100%;
            background: #ffffff;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .15rem;
            text-align: center;
            font-size: .72rem;
            font-weight: 800;
        }

        .qr-fallback small {
            font-size: .62rem;
            font-weight: 700;
            color: #64748b;
        }

        .resize-handle {
            position: absolute;
            width: 14px !important;
            height: 14px;
            right: -2px;
            bottom: -2px;
            background: #f59e0b;
            border-radius: 999px;
            border: 2px solid white;
            cursor: nwse-resize;
            padding: 0 !important;
        }

        .designer-note {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .9rem 1rem;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: .85rem;
        }

        .simple-settings-panel {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .simple-panel-section {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1rem;
            background: #ffffff;
        }

        .simple-panel-section h3 {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .simple-panel-section p {
            color: #64748b;
            font-size: .8rem;
            margin: .25rem 0 .8rem;
        }

        .simple-picker-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }

        .simple-picker-grid button {
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: .75rem;
            padding: .65rem;
            font-size: .78rem;
            font-weight: 700;
            color: #334155;
            text-align: left;
        }

        .simple-picker-grid button:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .selected-card {
            border: 1px solid #e5e7eb;
            border-radius: .85rem;
            padding: .8rem;
            background: #f8fafc;
            margin-bottom: .9rem;
        }

        .selected-card strong {
            display: block;
            margin-bottom: .55rem;
            color: #0f172a;
        }

        .selected-card label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .8rem;
            color: #475569;
        }

        .direction-control {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .45rem;
            margin-bottom: .9rem;
            padding: .75rem;
            border: 1px dashed #cbd5e1;
            border-radius: .85rem;
            background: #f8fafc;
        }

        .direction-control div {
            display: flex;
            gap: .45rem;
        }

        .direction-control button {
            width: 38px;
            height: 34px;
            border-radius: .65rem;
            border: 1px solid #cbd5e1;
            background: white;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
        }

        .direction-control button:hover {
            background: #eff6ff;
            border-color: #60a5fa;
            color: #1d4ed8;
        }

        .direction-control small {
            color: #64748b;
            font-size: .72rem;
            font-weight: 600;
        }

        .simple-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .7rem;
        }

        .simple-form-grid label {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            font-size: .75rem;
            font-weight: 700;
            color: #475569;
        }

        .simple-form-grid input,
        .simple-form-grid select {
            border: 1px solid #d1d5db;
            border-radius: .7rem;
            padding: .55rem .65rem;
            font-size: .85rem;
            color: #0f172a;
            background: white;
        }

        .panel-actions {
            display: flex;
            gap: .5rem;
            margin-top: .9rem;
        }

        .placeholder-list {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .placeholder-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: .75rem;
            padding: .65rem;
            text-align: left;
        }

        .placeholder-list-item.active {
            border-color: #38bdf8;
            background: #f0f9ff;
        }

        .placeholder-list-item span {
            font-size: .82rem;
            font-weight: 800;
            color: #0f172a;
        }

        .placeholder-list-item small {
            font-size: .7rem;
            color: #64748b;
        }

        .preview-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .preview-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .72);
        }

        .preview-card {
            position: relative;
            z-index: 1;
            width: min(96vw, 860px);
            max-height: 92vh;
            overflow: hidden;
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 30px 90px rgba(0, 0, 0, .35);
        }

        .preview-header,
        .preview-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .preview-footer {
            border-top: 1px solid #e5e7eb;
            border-bottom: none;
            justify-content: flex-end;
        }

        .preview-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .preview-header p {
            margin: .2rem 0 0;
            color: #64748b;
            font-size: .85rem;
        }

        .preview-header button {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            font-size: 1.5rem;
            line-height: 1;
        }

        .preview-body {
            padding: 1.5rem;
            background: #f8fafc;
            overflow: auto;
            max-height: 72vh;
            display: flex;
            justify-content: center;
        }

        .preview-canvas {
            transform: scale(.8);
            transform-origin: top center;
            flex-shrink: 0;
        }

        @media (max-width: 1300px) {
            .designer-layout {
                grid-template-columns: minmax(0, 1fr) 340px;
            }

            .workspace {
                padding: .6rem;
            }
        }

        @media (max-width: 1100px) {
            .designer-layout {
                grid-template-columns: 1fr;
            }

            .simple-settings-panel {
                order: -1;
            }
        }

        @media (max-width: 760px) {
            .designer-header,
            .template-summary {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: flex-start;
            }

            .designer-header {
                display: block;
            }

            .designer-actions {
                margin-top: 1rem;
            }

            .template-summary {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .simple-picker-grid,
            .simple-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function simpleCardDesigner(config) {
            return {
                placeholders: config.placeholders,
                selectedKey: config.selectedKey,
                zoom: config.zoom,
                showPreview: config.showPreview,
                templateWidth: config.templateWidth,
                templateHeight: config.templateHeight,
                sampleQrCodeUrl: config.sampleQrCodeUrl,
                defaultSampleQrCodeUrl: config.defaultSampleQrCodeUrl,

                dragging: null,
                resizing: null,

                init() {
                    if (!this.selectedKey && Object.keys(this.placeholders || {}).length > 0) {
                        this.selectedKey = Object.keys(this.placeholders)[0];
                    }

                    this.$nextTick(() => {
                        this.fitToScreen();
                    });
                },

                get current() {
                    if (!this.selectedKey || !this.placeholders) {
                        return null;
                    }

                    return this.placeholders[this.selectedKey] || null;
                },

                placeholderKey(placeholder) {
                    return String(
                        placeholder?.type
                        || placeholder?.key
                        || placeholder?.placeholder_key
                        || ''
                    ).toLowerCase();
                },

                isQr(placeholder) {
                    const key = this.placeholderKey(placeholder);

                    return [
                        'qr_code',
                        'qrcode',
                        'qr',
                        'qr-code',
                        'guest_qr_code',
                        'invitee_qr_code'
                    ].includes(key);
                },

                selectPlaceholder(key) {
                    this.selectedKey = key;
                },

                moveSelected(direction, fast = false) {
                    if (!this.current) {
                        return;
                    }

                    const step = fast ? 1 : 0.25;

                    if (direction === 'up') {
                        this.current.y_percent = clamp(
                            Number(this.current.y_percent || 0) - step,
                            0,
                            100 - Number(this.current.height_percent || 8)
                        );
                    }

                    if (direction === 'down') {
                        this.current.y_percent = clamp(
                            Number(this.current.y_percent || 0) + step,
                            0,
                            100 - Number(this.current.height_percent || 8)
                        );
                    }

                    if (direction === 'left') {
                        this.current.x_percent = clamp(
                            Number(this.current.x_percent || 0) - step,
                            0,
                            100 - Number(this.current.width_percent || 20)
                        );
                    }

                    if (direction === 'right') {
                        this.current.x_percent = clamp(
                            Number(this.current.x_percent || 0) + step,
                            0,
                            100 - Number(this.current.width_percent || 20)
                        );
                    }
                },

                fitToScreen() {
                    const workspace = this.$refs?.workspace;

                    if (!workspace) {
                        this.zoom = Number(this.zoom || 50);
                        return;
                    }

                    const availableWidth = Math.max(260, workspace.clientWidth - 8);
                    const availableHeight = Math.max(360, workspace.clientHeight - 8);

                    const widthZoom = (availableWidth / Number(this.templateWidth || 1080)) * 100;
                    const heightZoom = (availableHeight / Number(this.templateHeight || 1920)) * 100;

                    this.zoom = clamp(Math.min(widthZoom, heightZoom, 100), 25, 100);
                },

                increaseZoom() {
                    this.zoom = Math.min(160, Number(this.zoom || 100) + 5);
                },

                decreaseZoom() {
                    this.zoom = Math.max(25, Number(this.zoom || 100) - 5);
                },

                placeholderStyle(placeholder) {
                    return {
                        left: `${Number(placeholder.x_percent || 0)}%`,
                        top: `${Number(placeholder.y_percent || 0)}%`,
                        width: `${Number(placeholder.width_percent || 20)}%`,
                        height: `${Number(placeholder.height_percent || 8)}%`,
                        color: placeholder.font_color || '#000000',
                        fontFamily: placeholder.font_family || 'Montserrat',
                        fontSize: `${Number(placeholder.font_size || 16)}px`,
                        fontWeight: placeholder.font_weight === 'bold' ? '700' : '400',
                        textAlign: placeholder.text_align || 'center',
                        justifyContent: this.justifyContent(placeholder.text_align || 'center'),
                    };
                },

                justifyContent(align) {
                    if (align === 'left') {
                        return 'flex-start';
                    }

                    if (align === 'right') {
                        return 'flex-end';
                    }

                    return 'center';
                },

                previewValue(placeholder) {
                    const values = {
                        name: 'John Doe',
                        card_type: 'VIP',
                        qr_code: 'QR Code',
                        serial_number: 'ELC-0001',
                        guest_count: '2 Guests',
                        allowed_guests: '2',
                        table_number: 'Table 5',
                        category: 'Family',
                        event_name: 'Wedding Ceremony',
                        event_date: '25 Dec 2026',
                        event_time: '04:00 PM',
                        event_venue: 'Royal Hall',
                    };

                    return values[placeholder.key] || placeholder.label || 'Placeholder';
                },

                startDrag(event, key) {
                    const placeholder = this.placeholders[key];

                    this.selectedKey = key;

                    this.dragging = {
                        key,
                        startX: event.clientX,
                        startY: event.clientY,
                        originalX: Number(placeholder.x_percent || 0),
                        originalY: Number(placeholder.y_percent || 0),
                    };

                    document.addEventListener('mousemove', this.onDragMove);
                    document.addEventListener('mouseup', this.stopInteraction);
                },

                onDragMove: null,

                startResize(event, key) {
                    const placeholder = this.placeholders[key];

                    this.selectedKey = key;

                    this.resizing = {
                        key,
                        startX: event.clientX,
                        startY: event.clientY,
                        originalWidth: Number(placeholder.width_percent || 20),
                        originalHeight: Number(placeholder.height_percent || 8),
                    };

                    document.addEventListener('mousemove', this.onResizeMove);
                    document.addEventListener('mouseup', this.stopInteraction);
                },

                onResizeMove: null,

                stopInteraction: null,

                syncToLivewire() {
                    this.$wire.set('placeholders', JSON.parse(JSON.stringify(this.placeholders)));
                    this.$wire.set('selectedPlaceholder', this.selectedKey);
                    this.$wire.set('zoom', this.zoom);
                },
            }
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('simpleCardDesigner', (config) => {
                const designer = simpleCardDesigner(config);

                designer.onDragMove = function (event) {
                    if (!designer.dragging) {
                        return;
                    }

                    const item = designer.placeholders[designer.dragging.key];

                    const zoomRatio = Number(designer.zoom || 100) / 100;
                    const deltaX = (event.clientX - designer.dragging.startX) / zoomRatio;
                    const deltaY = (event.clientY - designer.dragging.startY) / zoomRatio;

                    const deltaXPercent = (deltaX / designer.templateWidth) * 100;
                    const deltaYPercent = (deltaY / designer.templateHeight) * 100;

                    item.x_percent = clamp(
                        designer.dragging.originalX + deltaXPercent,
                        0,
                        100 - Number(item.width_percent || 20)
                    );

                    item.y_percent = clamp(
                        designer.dragging.originalY + deltaYPercent,
                        0,
                        100 - Number(item.height_percent || 8)
                    );
                };

                designer.onResizeMove = function (event) {
                    if (!designer.resizing) {
                        return;
                    }

                    const item = designer.placeholders[designer.resizing.key];

                    const zoomRatio = Number(designer.zoom || 100) / 100;
                    const deltaX = (event.clientX - designer.resizing.startX) / zoomRatio;
                    const deltaY = (event.clientY - designer.resizing.startY) / zoomRatio;

                    const deltaWidthPercent = (deltaX / designer.templateWidth) * 100;
                    const deltaHeightPercent = (deltaY / designer.templateHeight) * 100;

                    const nextWidth = clamp(
                        designer.resizing.originalWidth + deltaWidthPercent,
                        1,
                        100 - Number(item.x_percent || 0)
                    );

                    const nextHeight = clamp(
                        designer.resizing.originalHeight + deltaHeightPercent,
                        1,
                        100 - Number(item.y_percent || 0)
                    );

                    if (designer.isQr(item)) {
                        const maxSquareSize = Math.min(
                            100 - Number(item.x_percent || 0),
                            100 - Number(item.y_percent || 0)
                        );

                        const squareSize = clamp(Math.max(nextWidth, nextHeight), 1, maxSquareSize);

                        item.width_percent = squareSize;
                        item.height_percent = squareSize;
                        item.qr_size = Math.round(squareSize * 10);
                    } else {
                        item.width_percent = nextWidth;
                        item.height_percent = nextHeight;
                    }
                };

                designer.stopInteraction = function () {
                    designer.dragging = null;
                    designer.resizing = null;

                    document.removeEventListener('mousemove', designer.onDragMove);
                    document.removeEventListener('mousemove', designer.onResizeMove);
                    document.removeEventListener('mouseup', designer.stopInteraction);
                };

                return designer;
            });
        });

        function clamp(value, min, max) {
            value = Number(value || 0);
            min = Number(min || 0);
            max = Number(max || 100);

            return Math.round(Math.max(min, Math.min(max, value)) * 10000) / 10000;
        }
    </script>
</x-filament-panels::page>