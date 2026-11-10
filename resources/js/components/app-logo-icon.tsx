import { SVGAttributes } from 'react';

/** Wiifarm mark: a sprout with two leaves rising from a furrow. Drawn for this project. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M15 28V15.5c0-.6.4-1 1-1s1 .4 1 1V28z" />
            <path d="M16 16.5C16 10 11.5 6 5 6c0 6.5 4.5 10.5 11 10.5z" />
            <path d="M16 14C16 7.8 20.3 4 26.5 4 26.5 10.2 22.2 14 16 14z" />
            <path d="M5 27.5c3.5-1.6 7.2-2.4 11-2.4s7.5.8 11 2.4V29H5z" />
        </svg>
    );
}
