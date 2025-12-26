import svgPaths from "./svg-u67olcqmrd";
import imgRectangle77Copy2 from "figma:asset/c3d6919b6048ba69e7232b37dcaafb9fc40064b8.png";
import imgRectangle77Copy3 from "figma:asset/b3d3593f5ca90d3383621a4237b5fc2686b8f2d7.png";

function Group() {
  return (
    <div className="absolute contents inset-0" data-name="Group">
      <div className="absolute inset-[6.36%_0_0_0]" data-name="Rectangle 77 copy 2">
        <img alt="" className="block max-w-none size-full" height="332.434" src={imgRectangle77Copy2} width="1095" />
      </div>
      <img alt="" className="block max-w-none size-full" height="355" src={imgRectangle77Copy3} width="1095" />
      <div className="absolute inset-[0_0_4.95%_0]" data-name="Rectangle 77 copy 3">
        <svg className="block size-full" fill="none" preserveAspectRatio="none" viewBox="0 0 32 32">
          <g id="Rectangle 77 copy 3"></g>
        </svg>
      </div>
    </div>
  );
}

function Bg() {
  return (
    <div className="absolute contents inset-0" data-name="Bg">
      <Group />
    </div>
  );
}

function Frame() {
  return (
    <div className="absolute content-stretch flex flex-col gap-[15px] items-start left-0 top-0 w-[527px]">
      <div className="h-[35px] relative shrink-0 w-[46.34px]" data-name="“">
        <svg className="block size-full" fill="none" preserveAspectRatio="none" viewBox="0 0 46.34 35">
          <path d={svgPaths.p95c4200} fill="var(--fill-0, #FAA100)" id="â" />
        </svg>
      </div>
      <p className="font-['Inter_Tight:SemiBold_Italic',sans-serif] font-semibold italic leading-[normal] min-w-full relative shrink-0 text-[#141414] text-[24px] w-[min-content]">The greatness of a nation and its moral progress can be judged by the way its animals are treated.</p>
    </div>
  );
}

function Frame1() {
  return (
    <div className="absolute content-stretch flex flex-col items-start left-0 top-[156px] w-[170px]">
      <p className="font-['Inter_Tight:Medium',sans-serif] leading-[normal] not-italic relative shrink-0 text-[16px] text-black text-nowrap">— Mahatma Gandhi</p>
    </div>
  );
}

function Frame2() {
  return (
    <div className="absolute h-[175px] left-[80px] top-[90px] w-[527px]">
      <Frame />
      <Frame1 />
    </div>
  );
}

function Frame3() {
  return (
    <div className="absolute h-[355px] left-0 top-0 w-[1095px]">
      <Bg />
      <Frame2 />
    </div>
  );
}

export default function Frame4() {
  return (
    <div className="relative size-full">
      <Frame3 />
    </div>
  );
}